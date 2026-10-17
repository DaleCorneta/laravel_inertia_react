<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ContactAttachmentService
{
    /** @param list<UploadedFile> $files */
    public function inspect(array $files): void
    {
        foreach ($files as $index => $file) {
            $mime = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());
            $contents = $file->get();
            $valid = match ($mime) {
                'application/pdf' => $extension === 'pdf' && str_starts_with($contents, '%PDF-') && preg_match('/%%EOF[\x20\t\r\n]*$/D', $contents) === 1,
                'image/png' => $extension === 'png' && $this->validImage($file, 'image/png') && str_ends_with($contents, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82"),
                'image/jpeg' => in_array($extension, ['jpg', 'jpeg'], true) && $this->validImage($file, 'image/jpeg') && str_ends_with($contents, "\xFF\xD9"),
                default => false,
            };

            if (! $valid || (config('demo.contact_attachments_strict_inspection') && $this->hasActiveContent($contents, $mime))) {
                throw ValidationException::withMessages([
                    "attachments.$index" => 'The attachment failed file format or security inspection.',
                ]);
            }
        }
    }

    /** @param list<UploadedFile> $files */
    public function store(Contact $contact, User $uploader, array $files, AuditLogger $auditLogger): void
    {
        $paths = [];

        try {
            foreach ($files as $file) {
                $path = $file->store('contact-attachments/'.$contact->getKey(), 'local');
                if ($path === false) {
                    throw new RuntimeException('Unable to store contact attachment.');
                }
                $paths[] = $path;

                $attachment = $contact->attachments()->create([
                    'uploaded_by' => $uploader->id,
                    'original_name' => $this->safeOriginalName($file),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                $auditLogger->log('contact.attachment_uploaded', $attachment);
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    public function delete(ContactAttachment $attachment, AuditLogger $auditLogger): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($attachment->path) && ! $disk->delete($attachment->path)) {
            throw new RuntimeException('Unable to remove contact attachment.');
        }

        $auditLogger->log('contact.attachment_deleted', $attachment);
        $attachment->delete();
    }

    public function purge(Contact $contact): void
    {
        $paths = $contact->attachments()->pluck('path')->all();
        $disk = Storage::disk('local');
        if ($paths !== [] && ! $disk->delete($paths)) {
            throw new RuntimeException('Unable to remove contact attachments.');
        }
        $contact->attachments()->delete();
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'attachment';

        return mb_substr($name === '' ? 'attachment' : $name, 0, 255);
    }

    private function hasActiveContent(string $contents, string $mime): bool
    {
        if (preg_match('/<\s*script\b|<\?php\b|<\?=|javascript:|<\s*html\b/i', $contents) === 1) {
            return true;
        }

        return $mime === 'application/pdf'
            && preg_match('/\/(?:JavaScript|JS|Launch|EmbeddedFile|RichMedia|OpenAction|AA)\b/i', $contents) === 1;
    }

    private function validImage(UploadedFile $file, string $expectedMime): bool
    {
        $image = @getimagesize($file->getPathname());

        return is_array($image) && ($image['mime'] ?? null) === $expectedMime;
    }
}

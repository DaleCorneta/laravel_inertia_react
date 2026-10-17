<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CommunityPostMediaService
{
    /** @param list<UploadedFile> $files */
    public function inspect(array $files): void
    {
        foreach ($files as $index => $file) {
            $mime = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());
            $contents = $file->get();
            $valid = match ($mime) {
                'image/png' => $extension === 'png' && is_string($contents) && str_starts_with($contents, "\x89PNG\r\n\x1A\n") && str_ends_with($contents, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82") && $this->validImage($file, $mime),
                'image/jpeg' => in_array($extension, ['jpg', 'jpeg'], true) && is_string($contents) && str_starts_with($contents, "\xFF\xD8\xFF") && str_ends_with($contents, "\xFF\xD9") && $this->validImage($file, $mime),
                default => false,
            };

            if (! $valid || (config('demo.community_post_media_strict_inspection') && is_string($contents) && preg_match('/<\s*script\b|<\?php\b|<\?=|javascript:|<\s*html\b/i', $contents) === 1)) {
                throw ValidationException::withMessages(["media.$index" => 'The image failed file format or security inspection.']);
            }
        }
    }

    /** @param list<UploadedFile> $files */
    public function store(CommunityPost $post, User $uploader, array $files, AuditLogger $auditLogger): void
    {
        $paths = [];

        try {
            foreach ($files as $file) {
                $path = $file->store('community-post-media/'.$post->getKey(), 'local');
                if ($path === false) {
                    throw new RuntimeException('Unable to store community post image.');
                }
                $paths[] = $path;
                $media = $post->media()->create([
                    'uploaded_by' => $uploader->id,
                    'original_name' => $this->safeOriginalName($file),
                    'path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                $auditLogger->log('community.media_uploaded', $media);
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
    }

    public function purge(CommunityPost $post): void
    {
        $paths = $post->media()->pluck('path')->all();
        if ($paths !== [] && ! Storage::disk('local')->delete($paths)) {
            throw new RuntimeException('Unable to remove community post images.');
        }
    }

    public function delete(CommunityPostMedia $media, AuditLogger $auditLogger): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($media->path) && ! $disk->delete($media->path)) {
            throw new RuntimeException('Unable to remove community post image.');
        }

        $auditLogger->log('community.media_deleted', $media);
        $media->delete();
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'image';

        return mb_substr($name === '' ? 'image' : $name, 0, 255);
    }

    private function validImage(UploadedFile $file, string $expectedMime): bool
    {
        $image = @getimagesize($file->getPathname());

        return is_array($image) && ($image['mime'] ?? null) === $expectedMime;
    }
}

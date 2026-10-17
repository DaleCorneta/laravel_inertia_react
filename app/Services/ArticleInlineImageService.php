<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleInlineMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ArticleInlineImageService
{
    public function store(Article $article, UploadedFile $image): ArticleInlineMedia
    {
        $mime = $image->getMimeType();
        $extension = strtolower($image->getClientOriginalExtension());
        $contents = $image->get();
        $valid = match ($mime) {
            'image/png' => $extension === 'png' && is_string($contents) && str_starts_with($contents, "\x89PNG\r\n\x1A\n") && str_ends_with($contents, "\x00\x00\x00\x00IEND\xAE\x42\x60\x82"),
            'image/jpeg' => in_array($extension, ['jpg', 'jpeg'], true) && is_string($contents) && str_starts_with($contents, "\xFF\xD8\xFF") && str_ends_with($contents, "\xFF\xD9"),
            default => false,
        };

        if (! $valid || (config('demo.blog_inline_images_strict_inspection') && is_string($contents) && preg_match('/<\s*script\b|<\?php\b|<\?=|javascript:|<\s*html\b/i', $contents) === 1)) {
            throw ValidationException::withMessages(['image' => 'The image failed file format or security inspection.']);
        }

        $path = $image->store('article-inline-media/'.$article->getKey(), 'local');
        if ($path === false) {
            throw new RuntimeException('Unable to store article image.');
        }

        try {
            return $article->inlineMedia()->create(['path' => $path, 'mime_type' => $mime]);
        } catch (\Throwable $exception) {
            $this->delete($path);
            throw $exception;
        }
    }

    public function delete(?string $path): void
    {
        if ($path !== null && Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
            throw new RuntimeException('Unable to remove article image.');
        }
    }
}

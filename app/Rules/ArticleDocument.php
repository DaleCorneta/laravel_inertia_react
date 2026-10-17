<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ArticleDocument implements ValidationRule
{
    private const MAX_NODES = 1000;

    private const MAX_TEXT_LENGTH = 50000;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nodes = 0;
        $textLength = 0;

        if (! is_array($value)
            || ! $this->hasOnlyKeys($value, ['type', 'content'])
            || ($value['type'] ?? null) !== 'doc'
            || ! is_array($value['content'] ?? null)
            || ! $this->validBlocks($value['content'], 0, $nodes, $textLength)) {
            $fail('The article content structure is invalid or contains unsupported content.');
        }
    }

    /** @param array<mixed> $nodes */
    private function validBlocks(mixed $nodes, int $depth, int &$count, int &$textLength): bool
    {
        if (! is_array($nodes) || ! array_is_list($nodes) || $depth > 10) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! is_array($node) || $count >= self::MAX_NODES) {
                return false;
            }
            $count++;

            $type = $node['type'] ?? null;

            if ($type === 'paragraph' || $type === 'heading') {
                if (! $this->hasOnlyKeys($node, $type === 'heading' ? ['type', 'attrs', 'content'] : ['type', 'content'])) {
                    return false;
                }

                if ($type === 'heading' && ! $this->validHeadingAttributes($node['attrs'] ?? null)) {
                    return false;
                }

                if (! $this->validInlineContent($node['content'] ?? [], $depth + 1, $count, $textLength)) {
                    return false;
                }

                continue;
            }

            if ($type === 'blockquote') {
                if (! $this->hasOnlyKeys($node, ['type', 'content'])
                    || ! $this->validBlocks($node['content'] ?? null, $depth + 1, $count, $textLength)) {
                    return false;
                }

                continue;
            }

            if ($type === 'bulletList' || $type === 'orderedList') {
                if (! $this->hasOnlyKeys($node, ['type', 'content']) || ! is_array($node['content'] ?? null) || ! array_is_list($node['content'])) {
                    return false;
                }

                foreach ($node['content'] as $item) {
                    if (! is_array($item) || $count >= self::MAX_NODES || ($item['type'] ?? null) !== 'listItem'
                        || ! $this->hasOnlyKeys($item, ['type', 'content'])) {
                        return false;
                    }
                    $count++;

                    if (! $this->validBlocks($item['content'] ?? null, $depth + 1, $count, $textLength)) {
                        return false;
                    }
                }

                continue;
            }

            return false;
        }

        return true;
    }

    /** @param array<mixed> $nodes */
    private function validInlineContent(mixed $nodes, int $depth, int &$count, int &$textLength): bool
    {
        if (! is_array($nodes) || ! array_is_list($nodes) || $depth > 10) {
            return false;
        }

        foreach ($nodes as $node) {
            if (! is_array($node) || $count >= self::MAX_NODES) {
                return false;
            }
            $count++;

            if (($node['type'] ?? null) === 'hardBreak') {
                if (! $this->hasOnlyKeys($node, ['type'])) {
                    return false;
                }

                continue;
            }

            if (($node['type'] ?? null) === 'articleImage') {
                $attributes = $node['attrs'] ?? null;
                if (! $this->hasOnlyKeys($node, ['type', 'attrs'])
                    || ! is_array($attributes)
                    || ! $this->hasOnlyKeys($attributes, ['mediaId', 'alt'])
                    || ! is_int($attributes['mediaId'] ?? null)
                    || $attributes['mediaId'] < 1
                    || ! is_string($attributes['alt'] ?? null)
                    || mb_strlen($attributes['alt']) > 255) {
                    return false;
                }

                continue;
            }

            if (($node['type'] ?? null) !== 'text'
                || ! $this->hasOnlyKeys($node, ['type', 'text', 'marks'])
                || ! is_string($node['text'] ?? null)
                || $node['text'] === '') {
                return false;
            }

            $textLength += mb_strlen($node['text']);
            if ($textLength > self::MAX_TEXT_LENGTH || ! $this->validMarks($node['marks'] ?? [])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed> $marks */
    private function validMarks(mixed $marks): bool
    {
        if (! is_array($marks) || ! array_is_list($marks) || count($marks) > 8) {
            return false;
        }

        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                return false;
            }

            if ($mark['type'] === 'link') {
                $href = $mark['attrs']['href'] ?? null;
                $attributes = $mark['attrs'] ?? null;
                $scheme = is_string($href) ? parse_url($href, PHP_URL_SCHEME) : false;

                if (! $this->hasOnlyKeys($mark, ['type', 'attrs'])
                    || ! is_array($attributes)
                    || ! $this->hasOnlyKeys($attributes, ['href', 'target', 'rel', 'class', 'title'])
                    || ! is_string($href)
                    || $href === ''
                    || mb_strlen($href) > 2048
                    || preg_match('/[\x00-\x20\\\\]/', $href)
                    || ($scheme !== null && ! in_array(strtolower((string) $scheme), ['http', 'https', 'mailto'], true))
                    || ($scheme === null && str_starts_with($href, '//'))
                    || ! in_array($attributes['target'] ?? null, [null, '_blank'], true)
                    || ! in_array($attributes['rel'] ?? null, [null, 'noopener noreferrer nofollow'], true)
                    || (($attributes['target'] ?? null) === '_blank' && ($attributes['rel'] ?? null) !== 'noopener noreferrer nofollow')
                    || ($attributes['class'] ?? null) !== null
                    || (($attributes['title'] ?? null) !== null && (! is_string($attributes['title']) || mb_strlen($attributes['title']) > 255))) {
                    return false;
                }

                continue;
            }

            if ($mark['type'] === 'textStyle') {
                if (! $this->hasOnlyKeys($mark, ['type', 'attrs']) || ! $this->validTextStyleAttributes($mark['attrs'] ?? null)) {
                    return false;
                }

                continue;
            }

            if (! in_array($mark['type'], ['bold', 'italic', 'strike', 'underline', 'code'], true)
                || ! $this->hasOnlyKeys($mark, ['type'])) {
                return false;
            }
        }

        return true;
    }

    /** @param array<mixed>|null $attributes */
    private function validTextStyleAttributes(mixed $attributes): bool
    {
        return is_array($attributes)
            && $this->hasOnlyKeys($attributes, ['fontFamily', 'fontSize', 'lineHeight'])
            && in_array($attributes['fontFamily'] ?? null, [null, 'Inter', 'Poppins', 'Montserrat'], true)
            && in_array($attributes['fontSize'] ?? null, [null, '12px', '14px', '16px', '18px', '24px', '32px'], true)
            && in_array($attributes['lineHeight'] ?? null, [null, '1.25', '1.5', '1.75', '2'], true);
    }

    /** @param array<mixed>|null $attributes */
    private function validHeadingAttributes(mixed $attributes): bool
    {
        if ($attributes === null) {
            return true;
        }

        return is_array($attributes)
            && $this->hasOnlyKeys($attributes, ['level'])
            && in_array($attributes['level'] ?? null, [1, 2, 3], true);
    }

    /**
     * @param  array<mixed>  $value
     * @param  list<string>  $allowed
     */
    private function hasOnlyKeys(array $value, array $allowed): bool
    {
        return array_diff(array_keys($value), $allowed) === [];
    }
}

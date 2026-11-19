<?php

namespace Qstom\Minimax;

use Qstom\Minimax\Exceptions\MinimaxException;

/**
 * A PDF returned by Minimax (base64 AttachmentData).
 */
final readonly class Pdf
{
    public function __construct(
        public string $filename,
        public string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $attachment  Attachment with AttachmentData and AttachmentFileName.
     */
    public static function fromAttachment(array $attachment, string $fallbackName): self
    {
        $content = base64_decode((string) ($attachment['AttachmentData'] ?? ''), true);

        if ($content === false || $content === '') {
            throw new MinimaxException('Minimax returned an unreadable PDF.');
        }

        $name = basename((string) ($attachment['AttachmentFileName'] ?? '')) ?: $fallbackName;

        return new self($name, $content);
    }
}

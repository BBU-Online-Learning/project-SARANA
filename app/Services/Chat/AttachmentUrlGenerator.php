<?php

namespace App\Services\Chat;

use App\Models\Attachment;
use DateTimeInterface;
use LogicException;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

class AttachmentUrlGenerator extends DefaultUrlGenerator
{
    private function isChatAttachment(): bool
    {
        return $this->media?->model_type === (new Attachment)->getMorphClass();
    }

    public function getUrl(): string
    {
        if (! $this->isChatAttachment()) {
            return parent::getUrl();
        }

        return route($this->conversion ? 'chat.attachments.thumbnail' : 'chat.attachments.show', $this->media->model_id);
    }

    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        return $this->isChatAttachment() ? $this->getUrl() : parent::getTemporaryUrl($expiration, $options);
    }

    public function getResponsiveImagesDirectoryUrl(): string
    {
        if ($this->isChatAttachment()) {
            throw new LogicException('Chat attachments require authenticated delivery.');
        }

        return parent::getResponsiveImagesDirectoryUrl();
    }

    public function getBaseMediaDirectoryUrl(): string
    {
        if ($this->isChatAttachment()) {
            throw new LogicException('Chat attachments require authenticated delivery.');
        }

        return parent::getBaseMediaDirectoryUrl();
    }
}

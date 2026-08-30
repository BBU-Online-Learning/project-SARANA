<?php

namespace App\Console\Commands;

use App\Models\SchoolClass;
use App\Services\ClassManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PrivatizeClassAvatars extends Command
{
    protected $signature = 'classes:privatize-avatars {--commit : Copy, verify, update the path and remove the old public copy}';

    protected $description = 'Preview or safely move existing class images out of public storage';

    public function handle(ClassManagementService $classes): int
    {
        $pending = 0;
        $failed = 0;
        SchoolClass::withTrashed()->whereNotNull('avatar')->orderBy('id')->chunkById(100,
            function ($candidates) use ($classes, &$pending, &$failed): void {
                foreach ($candidates as $candidate) {
                    try {
                        $path = $candidate->avatar;
                        if (! preg_match('~^(classes|class-avatars)/[A-Za-z0-9_-]+\\.(jpg|jpeg|png|webp)$~D', $path)) {
                            throw new RuntimeException('Unrecognized image path.');
                        }
                        $publicPath = 'classes/'.basename($path);
                        $privatePath = 'class-avatars/'.basename($path);
                        $public = Storage::disk('public');
                        $private = Storage::disk('local');
                        if (! $public->exists($publicPath) && $path === $privatePath && $private->exists($privatePath)) {
                            continue;
                        }
                        if (! $public->exists($publicPath) && ! $private->exists($privatePath)) {
                            throw new RuntimeException('The image is missing.');
                        }
                        if ($public->exists($publicPath) && $private->exists($privatePath)
                            && hash('sha256', $public->get($publicPath)) !== hash('sha256', $private->get($privatePath))) {
                            throw new RuntimeException('Image copies differ.');
                        }
                        $pending++;
                        if (! $this->option('commit')) {
                            continue;
                        }

                        $classes->synchronized(function () use ($candidate, $path, $privatePath, $publicPath, $public, $private): void {
                            $schoolClass = SchoolClass::withTrashed()->lockForUpdate()->findOrFail($candidate->id);
                            if ($schoolClass->avatar !== $path) {
                                throw new RuntimeException('The image changed during migration.');
                            }
                            if ($public->exists($publicPath)) {
                                $contents = $public->get($publicPath);
                                if (! $private->exists($privatePath) && ! $private->put($privatePath, $contents)) {
                                    throw new RuntimeException('Private copy failed.');
                                }
                                if (hash('sha256', $contents) !== hash('sha256', $private->get($privatePath))) {
                                    throw new RuntimeException('Copy verification failed.');
                                }
                            }
                            $schoolClass->update(['avatar' => $privatePath]);
                        });
                        // Delete only after the verified copy and database transaction commit.
                        if ($public->exists($publicPath) && ! $public->delete($publicPath)) {
                            throw new RuntimeException('Public cleanup failed; rerun this command.');
                        }
                    } catch (\Throwable) {
                        $failed++;
                        $this->error("Class {$candidate->id}: migration or cleanup refused. Review the image securely and retry.");
                    }
                }
            });
        $this->info(($this->option('commit') ? 'Processed' : 'Pending').' images: '.$pending.'. Failed: '.$failed.'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

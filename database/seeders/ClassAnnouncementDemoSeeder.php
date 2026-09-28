<?php

namespace Database\Seeders;

use App\Models\ClassAnnouncement;
use App\Models\SchoolClassChannel;
use App\Models\User;
use App\Services\ClassAnnouncementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Gate;

class ClassAnnouncementDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $channel = SchoolClassChannel::query()->where('slug', 'announcement')->where('is_default', true)
            ->whereHas('schoolClass', fn ($query) => $query->whereNull('archived_at'))->first();
        if (! $channel || $channel->notices()->where('title', 'Example class notice')->exists()) {
            return;
        }

        $schoolClass = $channel->schoolClass;
        $teacherId = $schoolClass->memberRecords()->whereIn('role', ['owner', 'teacher'])->value('user_id');
        $teacher = $teacherId ? User::query()->find($teacherId) : null;
        if ($teacher && Gate::forUser($teacher)->allows('create', [ClassAnnouncement::class, $schoolClass, $channel])) {
            app(ClassAnnouncementService::class)->create($teacher, $schoolClass, $channel, [
                'title' => 'Example class notice',
                'body' => 'Update this draft before publishing it to the class.',
            ]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Announcements\SaveClassAnnouncementRequest;
use App\Http\Requests\Announcements\ScheduleClassAnnouncementRequest;
use App\Models\ClassAnnouncement;
use App\Models\SchoolClass;
use App\Models\SchoolClassChannel;
use App\Services\ClassAnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClassAnnouncementController extends Controller
{
    public function store(SaveClassAnnouncementRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->create($request->user(), $schoolClass, $channel, $request->validated());

        return $this->backToChannel($schoolClass, $channel, 'Notice saved as a draft.');
    }

    public function update(SaveClassAnnouncementRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->update($request->user(), $schoolClass, $channel, $notice, $request->validated());

        return $this->backToChannel($schoolClass, $channel, 'Notice updated.');
    }

    public function schedule(ScheduleClassAnnouncementRequest $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->schedule($request->user(), $schoolClass, $channel, $notice, $request->validated('publish_at'));

        return $this->backToChannel($schoolClass, $channel, 'Notice scheduled.');
    }

    public function returnToDraft(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->returnToDraft($request->user(), $schoolClass, $channel, $notice);

        return $this->backToChannel($schoolClass, $channel, 'Notice returned to draft.');
    }

    public function publish(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->publish($request->user(), $schoolClass, $channel, $notice);

        return $this->backToChannel($schoolClass, $channel, 'Notice published.');
    }

    public function pin(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->pin($request->user(), $schoolClass, $channel, $notice, true);

        return $this->backToChannel($schoolClass, $channel, 'Notice pinned.');
    }

    public function unpin(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->pin($request->user(), $schoolClass, $channel, $notice, false);

        return $this->backToChannel($schoolClass, $channel, 'Notice unpinned.');
    }

    public function archive(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->archive($request->user(), $schoolClass, $channel, $notice);

        return $this->backToChannel($schoolClass, $channel, 'Notice archived.');
    }

    public function restore(Request $request, SchoolClass $schoolClass, SchoolClassChannel $channel, ClassAnnouncement $notice, ClassAnnouncementService $notices): RedirectResponse
    {
        $notices->restore($request->user(), $schoolClass, $channel, $notice);

        return $this->backToChannel($schoolClass, $channel, 'Notice restored as a draft.');
    }

    private function backToChannel(SchoolClass $schoolClass, SchoolClassChannel $channel, string $message): RedirectResponse
    {
        return redirect()->route('classes.channels.show', [$schoolClass, $channel])->with('success', $message);
    }
}

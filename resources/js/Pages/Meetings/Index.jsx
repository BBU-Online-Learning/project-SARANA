import React from 'react';
import { Head, Link } from '@inertiajs/react';

const groups = [
    { key: 'ready', title: 'Ready to join', description: 'The room is open now.', icon: 'ti-video' },
    { key: 'upcoming', title: 'Coming up', description: 'Your next scheduled class sessions.', icon: 'ti-calendar-event' },
    { key: 'earlier', title: 'Earlier meetings', description: 'Past and cancelled sessions.', icon: 'ti-history' },
];

function Status({ value }) {
    const tone = value === 'cancelled' ? 'danger' : value === 'rescheduled' ? 'warning' : 'info';
    return <span className={`class-status class-status--${tone}`}>{value.charAt(0).toUpperCase() + value.slice(1)}</span>;
}

function MeetingCard({ meeting }) {
    return <article className={`card class-list-card meeting-card ${meeting.status === 'cancelled' ? 'is-cancelled' : ''}`}>
        <div className="card-body">
            <div className="meeting-card-top">
                <div className="meeting-date-tile" aria-hidden="true"><span>{meeting.month}</span><strong>{meeting.day}</strong></div>
                <div className="d-flex flex-wrap gap-1 justify-content-end"><Status value={meeting.status} />{meeting.rescheduled && <Status value="rescheduled" />}</div>
            </div>
            <h3 className="meeting-card-title"><a href={meeting.detailsUrl}>{meeting.title}</a></h3>
            <p className="meeting-card-meta"><i className="ti ti-clock" aria-hidden="true" /> {meeting.time}</p>
            <p className="meeting-card-meta"><i className="ti ti-user" aria-hidden="true" /> {meeting.host}</p>
            {meeting.repeat && <p className="meeting-card-meta"><i className="ti ti-repeat" aria-hidden="true" /> {meeting.repeat}</p>}
            <div className="meeting-card-actions">
                <a className="btn btn-outline-primary" href={meeting.detailsUrl}>View details <i className="ti ti-arrow-right" aria-hidden="true" /></a>
                {meeting.roomUrl && <a className="btn btn-primary" href={meeting.roomUrl}><i className="ti ti-video" aria-hidden="true" /> Join meeting</a>}
            </div>
        </div>
    </article>;
}

export default function Index({ title, schoolClass, classesUrl, sections, scheduleUrl, meetings, pagination }) {
    return <div className="page-container my-3">
        <Head title={title} />
        <nav aria-label="Breadcrumb" className="class-section-breadcrumb">
            <ol className="breadcrumb mb-3">
                <li className="breadcrumb-item"><a href={classesUrl}>Classes</a></li>
                <li className="breadcrumb-item">{schoolClass.overviewUrl ? <a href={schoolClass.overviewUrl}>{schoolClass.name}</a> : schoolClass.name}</li>
                <li className="breadcrumb-item active" aria-current="page">Class meetings</li>
            </ol>
        </nav>
        <header className="card class-hero class-section-hero mb-3">
            <div className="card-body d-flex flex-column flex-lg-row align-items-start justify-content-between gap-3">
                <div>
                    <div className="d-flex flex-wrap align-items-center gap-2 mb-2"><span className="badge class-hero-badge">Meetings</span>{schoolClass.archived && <span className="badge bg-secondary">Archived class</span>}</div>
                    <h1 className="class-title h3 mb-2">Class meetings</h1>
                    <p className="class-description mb-0">Join live lessons, see what is next, and review earlier meetings.</p>
                    <p className="small text-muted mt-2 mb-0">{schoolClass.name}{schoolClass.academicYear && ` · ${schoolClass.academicYear}`}</p>
                </div>
                {scheduleUrl && <div className="d-flex flex-wrap gap-2 class-section-actions"><a className="btn btn-primary" href={scheduleUrl}>Schedule meeting</a></div>}
            </div>
        </header>
        <nav className="learning-class-nav class-section-nav" aria-label="Class sections">
            {sections.map((section) => <a key={section.label} href={section.url} className={section.label === 'Meetings' ? 'is-active' : undefined} aria-current={section.label === 'Meetings' ? 'page' : undefined}>{section.label}</a>)}
        </nav>
        {meetings.length === 0 && <div className="class-empty"><span className="class-empty-icon" aria-hidden="true"><i className="ti ti-calendar-event" /></span><p className="class-empty-title">No meetings have been scheduled for this class.</p><p className="class-empty-description">New class meetings will appear here with their dates and status.</p></div>}
        {groups.map((group) => {
            const items = meetings.filter((meeting) => meeting.group === group.key);
            if (items.length === 0) return null;
            return <section key={group.key} className="meeting-list-section mb-4" aria-label={group.title}>
                <div className="meeting-list-heading"><div className="meeting-list-heading-icon"><i className={`ti ${group.icon}`} aria-hidden="true" /></div><div><h2 className="h5 mb-1">{group.title}</h2><p className="text-muted mb-0">{group.description}</p></div></div>
                <div className="meeting-card-grid">{items.map((meeting) => <MeetingCard key={meeting.id} meeting={meeting} />)}</div>
            </section>;
        })}
        {pagination.lastPage > 1 && <nav className="d-flex align-items-center justify-content-between gap-3" aria-label="Meeting pages">
            {pagination.previousUrl ? <Link className="btn btn-outline-primary" href={pagination.previousUrl}>Previous</Link> : <span />}
            <span>Page {pagination.page} of {pagination.lastPage}</span>
            {pagination.nextUrl ? <Link className="btn btn-outline-primary" href={pagination.nextUrl}>Next</Link> : <span />}
        </nav>}
    </div>;
}

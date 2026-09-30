import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';

function Summary({ summary, urls }) {
    return <section className="classes-summary" aria-label="Class summary">
        <Link href={urls.index} only={['classes', 'filters', 'summary']}><span className="is-blue"><i className="ti ti-layout-grid" /></span><span><strong>{summary.total}</strong><small>Total classes</small></span></Link>
        <Link href={urls.active} only={['classes', 'filters', 'summary']}><span className="is-teal"><i className="ti ti-circle-check" /></span><span><strong>{summary.active}</strong><small>Active classes</small></span></Link>
        <Link href={urls.archived} only={['classes', 'filters', 'summary']}><span className="is-amber"><i className="ti ti-archive" /></span><span><strong>{summary.archived}</strong><small>Archived classes</small></span></Link>
    </section>;
}

function FieldError({ message }) {
    return message && <p className="text-danger small mt-1">{message}</p>;
}

function Enrollment({ canCreateClass, isAdministrator, role, eligibleTeachers, urls, oldInput, errors }) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    return <>
        {canCreateClass && <details className="card mb-3 workspace-anchor enrollment-disclosure" id="create-class" open={Boolean(errors.owner_id || errors.name || errors.description || errors.avatar)}>
            <summary><span className="enrollment-summary-icon"><i className="ti ti-school" aria-hidden="true" /></span><span><strong>Create Class</strong><small>Set up a new teaching space</small></span><i className="ti ti-chevron-down enrollment-chevron" aria-hidden="true" /></summary>
            <div className="card-body">
                <div className="enrollment-form-heading"><span><i className="ti ti-sparkles" aria-hidden="true" /></span><div><h2>Create your class</h2><p>{isAdministrator ? 'Choose a teacher owner for the new class.' : 'Add the details students will see in their learning space.'}</p></div></div>
                <form method="POST" action={urls.create} encType="multipart/form-data" data-pending-form="">
                    <input type="hidden" name="_token" value={csrfToken} />
                    {isAdministrator && <div className="mb-3"><label className="form-label" htmlFor="enrollment-owner">Teacher Owner</label>
                        <select id="enrollment-owner" name="owner_id" className="form-select" defaultValue={oldInput.ownerId} required>
                            <option value="">Select an eligible Teacher</option>
                            {eligibleTeachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.name}</option>)}
                        </select>
                        <small className="text-muted">Creating a class does not enroll you for message access.</small><FieldError message={errors.owner_id} />
                    </div>}
                    <div className="mb-3"><label className="form-label" htmlFor="enrollment-name">Class Name</label>
                        <input type="text" id="enrollment-name" name="name" className="form-control" defaultValue={oldInput.name} required /><FieldError message={errors.name} />
                    </div>
                    <div className="mb-3"><label className="form-label" htmlFor="enrollment-description">Description</label>
                        <textarea id="enrollment-description" name="description" className="form-control" rows="3" defaultValue={oldInput.description} /><FieldError message={errors.description} />
                    </div>
                    <div className="mb-3"><label className="form-label" htmlFor="enrollment-avatar">Class Image</label>
                        <input type="file" id="enrollment-avatar" name="avatar" className="form-control" accept=".jpg,.jpeg,.png,.webp" aria-describedby="class-image-help" />
                        <small id="class-image-help" className="text-muted">Optional. Choose a JPG, PNG, or WebP image up to 2 MB.</small><FieldError message={errors.avatar} />
                    </div>
                    <button type="submit" className="btn btn-primary w-100" data-pending-label="Creating…">Create Class</button>
                    <p className="small mb-0 mt-2" role="status" data-form-status="" />
                </form>
            </div>
        </details>}
        <details className="card workspace-anchor enrollment-disclosure" id="join-class" open={Boolean(errors.join_code)}>
            <summary><span className="enrollment-summary-icon"><i className="ti ti-login" aria-hidden="true" /></span><span><strong>Join Class</strong><small>{isAdministrator ? 'Use a code to access class content' : role === 'teacher' ? 'Join as a student member' : 'Use a code from your teacher'}</small></span><i className="ti ti-chevron-down enrollment-chevron" aria-hidden="true" /></summary>
            <div className="card-body">
                <div className="enrollment-form-heading"><span><i className="ti ti-key" aria-hidden="true" /></span><div><h2>Enter a class code</h2><p>{isAdministrator ? 'Enroll your account to access class messages and learning content.' : 'Join an existing space as a student member.'}</p></div></div>
                <form method="POST" action={urls.join} data-pending-form="">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <div className="mb-3"><label className="form-label" htmlFor="enrollment-code">Join Code</label>
                        <input type="text" id="enrollment-code" name="join_code" className="form-control" defaultValue={oldInput.joinCode} required /><FieldError message={errors.join_code} />
                    </div>
                    <button type="submit" className="btn btn-primary w-100" data-pending-label="Joining…">Join Class</button>
                    <p className="small mb-0 mt-2" role="status" data-form-status="" />
                </form>
            </div>
        </details>
    </>;
}

function Filters({ filters, urls }) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);
    useEffect(() => { setSearch(filters.search); setStatus(filters.status); }, [filters.search, filters.status]);

    function submit(event) {
        event.preventDefault();
        router.get(urls.index, { search, status }, { only: ['classes', 'filters', 'summary'], preserveState: true, replace: true });
    }

    return <form onSubmit={submit} className="class-filters classes-modern-filters" role="search" aria-label="Find classes">
        <div className="flex-grow-1 classes-search-field"><label htmlFor="class-search" className="form-label">Search classes</label>
            <div><i className="ti ti-search" aria-hidden="true" /><input id="class-search" type="search" value={search} onChange={(event) => setSearch(event.target.value)} maxLength="100" className="form-control" placeholder="Class name or description" /></div>
        </div>
        <div><label htmlFor="class-status" className="form-label">Status</label>
            <select id="class-status" value={status} onChange={(event) => setStatus(event.target.value)} className="form-select">
                <option value="all">All classes</option><option value="active">Active classes</option><option value="archived">Archived classes</option>
            </select>
        </div>
        <button className="btn btn-primary" type="submit">Apply filters</button>
        {(filters.search !== '' || filters.status !== 'all') && <Link className="btn btn-outline-secondary" href={urls.index} only={['classes', 'filters', 'summary']}>Clear filters</Link>}
    </form>;
}

function LearningCard({ schoolClass }) {
    return <article className="learning-class-card">
        <div className="learning-class-mark" aria-hidden="true"><i className="ti ti-school" /></div>
        <div className="learning-class-copy"><span className={`learning-status ${schoolClass.archived ? 'is-archived' : ''}`}>{schoolClass.archived ? 'Archived' : 'Active class'}</span>
            <h3><a href={schoolClass.showUrl}>{schoolClass.name}</a></h3>
            <p>{schoolClass.description || 'Open this class to explore its channels and discussions.'}</p>
            <div className="learning-class-meta"><span>{schoolClass.memberCount} members</span><span>{schoolClass.channelCount} channels</span></div>
            {schoolClass.creatorName && <p className="learning-class-meta">Created by {schoolClass.creatorName}</p>}
            {schoolClass.joinCode && <p className="learning-class-meta">Join code: <strong>{schoolClass.joinCode}</strong></p>}
        </div>
        <div className="learning-class-links"><a className="btn btn-outline-primary" href={schoolClass.showUrl}>Open Class <i className="ti ti-arrow-right" aria-hidden="true" /></a>
            {schoolClass.canManage && <a className="learning-manage-link" href={`${schoolClass.showUrl}#class-actions`}>Manage class</a>}
        </div>
    </article>;
}

function AdministrationCard({ schoolClass }) {
    return <div className={`class-list-card ${schoolClass.archived ? 'is-archived' : 'is-active'} mb-3`}>
        <div className="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div className="flex-grow-1"><div className="d-flex flex-wrap align-items-center gap-2 mb-2"><span className="badge class-hero-badge">Class</span>
                {schoolClass.archived && <span className="badge bg-warning text-dark">Archived</span>}
                <span className="workspace-badge class-channel-badge"><i className="ti ti-hash" aria-hidden="true" />{schoolClass.channelCount} channels</span>
            </div><h3 className="h6 mb-1"><a href={schoolClass.showUrl} className="text-decoration-none">{schoolClass.name}</a></h3>
                <div className="text-muted small">{schoolClass.description || 'No description yet.'}</div>
            </div>
            <div className="d-flex flex-column align-items-stretch gap-2"><div className="class-meta-pill class-meta-pill-sm class-member-pill"><span>Members</span><strong>{schoolClass.memberCount}</strong></div>
                <a href={schoolClass.showUrl} className="btn btn-primary">Manage Class</a>
            </div>
        </div>
    </div>;
}

export default function Index({ title, role, isAdministrator, canCreateClass, introduction, summary, filters, urls, oldInput, eligibleTeachers, classes }) {
    const { errors = {} } = usePage().props;
    const isLearningRole = role === 'teacher' || role === 'student';
    const enrollment = <Enrollment canCreateClass={canCreateClass} isAdministrator={isAdministrator} role={role} eligibleTeachers={eligibleTeachers} urls={urls} oldInput={oldInput} errors={errors} />;

    return <div className={`page-container ${isLearningRole ? 'learning-page' : ''}`}>
        <Head title={title} />
        {isLearningRole ? <>
            <section className="classes-page-hero" aria-labelledby="classes-page-title"><div className="classes-page-hero-copy">
                <p className="learning-eyebrow">{role === 'teacher' ? 'Teaching workspace' : 'Learning workspace'}</p>
                <h1 id="classes-page-title">My Classes</h1><p>{introduction}</p>
                <div className="classes-page-actions">{canCreateClass && <a className="btn classes-primary-action" href="#create-class"><i className="ti ti-plus" aria-hidden="true" /> Create Class</a>}
                    <a className="btn classes-secondary-action" href="#join-class"><i className="ti ti-login" aria-hidden="true" /> Join Class</a></div>
            </div><div className="classes-page-hero-art" aria-hidden="true"><i className="ti ti-books" /></div></section>
            <Summary summary={summary} urls={urls} />
            <section className="learning-enrollment classes-enrollment" aria-label={canCreateClass ? 'Create or join a class' : 'Join a class'}>
                <header className="classes-section-heading"><div><p>{canCreateClass ? 'Class setup' : 'Class enrollment'}</p><h2>{canCreateClass ? 'Create or join a space' : 'Join a class'}</h2>
                    <span>{canCreateClass ? 'Use the options below to start teaching or enter an existing classroom.' : 'Enter the class code from your teacher to join your learning space.'}</span></div></header>
                {enrollment}
            </section>
            <section className="classes-directory" aria-labelledby="classes-directory-title"><header className="classes-section-heading"><div><p>Class directory</p><h2 id="classes-directory-title">Your class spaces</h2><span>Search, open, and manage the classes available to you.</span></div><strong>{classes.length} shown</strong></header>
                <Filters filters={filters} urls={urls} />
                <div className="learning-class-grid classes-page-grid">{classes.length ? classes.map((schoolClass) => <LearningCard key={schoolClass.id} schoolClass={schoolClass} />)
                    : filters.search || filters.status !== 'all' ? <p className="p-3">No classes match these filters. Try another search or <Link href={urls.index} only={['classes', 'filters', 'summary']}>clear filters</Link>.</p>
                        : <div className="learning-empty"><i className="ti ti-school" aria-hidden="true" /><h3>No classes yet</h3><p>{canCreateClass ? 'Create your first class or join an existing class with a code.' : 'Use a code from your teacher to join your first class.'}</p></div>}</div>
            </section>
        </> : <>
            <div className="card class-hero mb-4"><div className="card-body">{urls.academics && <a className="btn btn-outline-primary btn-sm mb-3" href={urls.academics}>Manage academic years, grades and subjects</a>}
                <div className="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3"><div className="flex-grow-1"><span className="badge class-hero-badge">School management</span>
                    <h1 className="class-title h3 mb-2">Class administration</h1><p className="class-description mb-0">{introduction}</p></div>
                    <div className="class-meta-pill"><span>Available actions</span><strong>{canCreateClass ? 'Create, Join, Open' : 'Join, Open'}</strong></div>
                </div></div></div>
            <div className="row g-3"><div className="col-lg-4">{enrollment}</div><div className="col-lg-8"><div className="card"><div className="card-body"><div className="d-flex justify-content-between align-items-center mb-3"><div><h2 className="h5 mb-1">Institution Classes</h2><p className="text-muted mb-0 small">Open a class to manage its information and membership.</p></div>
                <span className="workspace-badge class-total-badge">{classes.length} total</span></div>
                <Filters filters={filters} urls={urls} />
                {classes.length ? classes.map((schoolClass) => <AdministrationCard key={schoolClass.id} schoolClass={schoolClass} />) : <div className="alert alert-light mb-0">{filters.search || filters.status !== 'all' ? 'No classes match these filters. Try another search or clear filters.' : 'No institution classes yet. Create a class and assign a teacher owner to get started.'}</div>}
            </div></div></div></div>
        </>}
    </div>;
}

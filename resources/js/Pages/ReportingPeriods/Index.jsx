import React, { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';

const emptyPeriod = {
    academic_year_id: '', parent_id: '', name: '', code: '', sequence: 1, starts_on: '', ends_on: '',
};

export default function Index({ title, years, periods, urls, success }) {
    const { errors: pageErrors = {} } = usePage().props;
    const [editingId, setEditingId] = useState(null);
    const [selectedYearId, setSelectedYearId] = useState(years[0]?.id ?? '');
    const form = useForm({ ...emptyPeriod, academic_year_id: years[0]?.id ?? '' });
    const selectedYear = years.find((year) => String(year.id) === String(form.data.academic_year_id));
    const parentOptions = periods.filter((period) => String(period.academicYearId) === String(form.data.academic_year_id) && period.id !== editingId);
    const visiblePeriods = periods.filter((period) => String(period.academicYearId) === String(selectedYearId));

    function edit(period) {
        setEditingId(period.id);
        form.setData({
            academic_year_id: period.academicYearId, parent_id: period.parentId ?? '', name: period.name,
            code: period.code, sequence: period.sequence, starts_on: period.startsOn, ends_on: period.endsOn,
        });
        form.clearErrors();
        document.getElementById('reporting-period-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function reset() {
        setEditingId(null);
        form.setData({ ...emptyPeriod, academic_year_id: selectedYearId });
        form.clearErrors();
    }

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editingId) {
            form.patch(`${urls.index}/${editingId}`, options);
        } else {
            form.post(urls.store, options);
        }
    }

    function transition(period, status) {
        router.post(`${urls.index}/${period.id}/transition`, { status }, { preserveScroll: true });
    }

    return <div className="page-container my-3">
        <Head title={title} />
        <a className="btn btn-link px-0 mb-2" href={urls.academics}>Back to academic structure</a>
        <h1 className="h3 mb-2">Reporting periods</h1>
        <p className="text-muted">Define terms or other reporting windows within an academic year. Open a period when ready, then close it when reporting is complete.</p>
        {success && <div className="alert alert-success" role="status">{success}</div>}
        {pageErrors.reporting_period && <div className="alert alert-danger" role="alert">{pageErrors.reporting_period}</div>}
        {pageErrors.status && <div className="alert alert-danger" role="alert">{pageErrors.status}</div>}
        <div className="row g-3">
            <div className="col-lg-5"><section className="card" id="reporting-period-form"><div className="card-body">
                <h2 className="h5 mb-3">{editingId ? 'Edit draft period' : 'Add reporting period'}</h2>
                {years.length === 0 ? <p className="mb-0">Create a dated academic year first.</p> : <form onSubmit={submit}>
                    <div className="mb-3"><label className="form-label" htmlFor="period-year">Academic year</label>
                        <select id="period-year" className="form-select" value={form.data.academic_year_id} onChange={(event) => form.setData('academic_year_id', event.target.value)} disabled={Boolean(editingId)} required>
                            {years.map((year) => <option value={year.id} key={year.id}>{year.name} ({year.startsOn} to {year.endsOn})</option>)}
                        </select>{form.errors.academic_year_id && <small className="text-danger">{form.errors.academic_year_id}</small>}</div>
                    <div className="mb-3"><label className="form-label" htmlFor="period-parent">Parent period (optional)</label>
                        <select id="period-parent" className="form-select" value={form.data.parent_id} onChange={(event) => form.setData('parent_id', event.target.value)}>
                            <option value="">No parent</option>{parentOptions.map((period) => <option value={period.id} key={period.id}>{period.name}</option>)}
                        </select>{form.errors.parent_id && <small className="text-danger">{form.errors.parent_id}</small>}</div>
                    <div className="row g-3 mb-3"><div className="col-sm-8"><label className="form-label" htmlFor="period-name">Name</label>
                        <input id="period-name" className="form-control" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} maxLength="100" required />
                        {form.errors.name && <small className="text-danger">{form.errors.name}</small>}</div>
                        <div className="col-sm-4"><label className="form-label" htmlFor="period-code">Code</label>
                            <input id="period-code" className="form-control" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} maxLength="100" required />
                            {form.errors.code && <small className="text-danger">{form.errors.code}</small>}</div></div>
                    <div className="mb-3"><label className="form-label" htmlFor="period-sequence">Display order</label>
                        <input id="period-sequence" className="form-control" type="number" min="1" max="65535" value={form.data.sequence} onChange={(event) => form.setData('sequence', event.target.value)} required />
                        {form.errors.sequence && <small className="text-danger">{form.errors.sequence}</small>}</div>
                    <div className="row g-3 mb-3"><div className="col-sm-6"><label className="form-label" htmlFor="period-start">Start date</label>
                        <input id="period-start" className="form-control" type="date" min={selectedYear?.startsOn} max={selectedYear?.endsOn} value={form.data.starts_on} onChange={(event) => form.setData('starts_on', event.target.value)} required />
                        {form.errors.starts_on && <small className="text-danger">{form.errors.starts_on}</small>}</div>
                        <div className="col-sm-6"><label className="form-label" htmlFor="period-end">End date</label>
                            <input id="period-end" className="form-control" type="date" min={selectedYear?.startsOn} max={selectedYear?.endsOn} value={form.data.ends_on} onChange={(event) => form.setData('ends_on', event.target.value)} required />
                            {form.errors.ends_on && <small className="text-danger">{form.errors.ends_on}</small>}</div></div>
                    <div className="d-flex gap-2"><button className="btn btn-primary" type="submit" disabled={form.processing}>{editingId ? 'Save changes' : 'Create draft'}</button>
                        {editingId && <button className="btn btn-outline-secondary" type="button" onClick={reset}>Cancel</button>}</div>
                </form>}
            </div></section></div>
            <div className="col-lg-7"><section className="card"><div className="card-body">
                <div className="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-3"><div><h2 className="h5 mb-1">Periods</h2><p className="text-muted small mb-0">Draft → open → closed</p></div>
                    {years.length > 0 && <div><label className="form-label" htmlFor="period-list-year">Show academic year</label>
                        <select id="period-list-year" className="form-select" value={selectedYearId} onChange={(event) => setSelectedYearId(event.target.value)}>
                            {years.map((year) => <option value={year.id} key={year.id}>{year.name}</option>)}
                        </select></div>}</div>
                {visiblePeriods.length === 0 ? <p className="mb-0">No reporting periods for this academic year.</p> : <div className="list-group list-group-flush">
                    {visiblePeriods.map((period) => <article className="list-group-item px-0" key={period.id}>
                        <div className="d-flex flex-wrap justify-content-between gap-2"><div><h3 className="h6 mb-1">{period.name} <span className="badge bg-secondary">{period.status}</span></h3>
                            <p className="text-muted small mb-1">{period.code} · {period.startsOn} to {period.endsOn}</p>
                            {period.parentId && <p className="small mb-0">Within {periods.find((parent) => parent.id === period.parentId)?.name ?? 'parent period'}</p>}</div>
                            <div className="d-flex gap-2 align-items-start">{period.status === 'draft' && <><button className="btn btn-sm btn-outline-primary" type="button" onClick={() => edit(period)}>Edit</button>
                                <button className="btn btn-sm btn-primary" type="button" onClick={() => transition(period, 'open')}>Open</button></>}
                                {period.status === 'open' && <button className="btn btn-sm btn-outline-secondary" type="button" onClick={() => transition(period, 'closed')}>Close</button>}</div></div>
                    </article>)}
                </div>}
            </div></section></div>
        </div>
    </div>;
}

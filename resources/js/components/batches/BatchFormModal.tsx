import React, { useState, useEffect } from 'react';
import { Batch, BatchFormData, Teacher, WEEK_DAYS } from '../../types';
import { batchService } from '../../services';
import { Alert, LoadingSpinner } from '../common';
import { ApiErrorResponse } from '../../types/api';

interface BatchFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (batch: Batch) => void;
  batch?: Batch | null;
  teachers: Teacher[];
}

export const BatchFormModal: React.FC<BatchFormModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  batch,
  teachers,
}) => {
  const isEditing = !!batch;
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const [formData, setFormData] = useState<BatchFormData>({
    batchnum: '',
    totalseat: '30',
    stuff_id: null,
    startdate: '',
    enddate: '',
    day1: 0,
    schedule1: '',
    day2: 0,
    schedule2: '',
    day3: 0,
    schedule3: '',
    day4: 0,
    schedule4: '',
    day5: 0,
    schedule5: '',
    day6: 0,
    schedule6: '',
    day7: 0,
    schedule7: '',
    IsActive: 1, // Running
  });

  useEffect(() => {
    if (batch) {
      setFormData({
        batchnum: batch.batchnum || '',
        totalseat: batch.totalseat || '30',
        stuff_id: batch.stuff_id ?? null,
        startdate: batch.startdate ? batch.startdate.split('T')[0] : '',
        enddate: batch.enddate ? batch.enddate.split('T')[0] : '',
        day1: batch.day1 ?? 0,
        schedule1: batch.schedule1 || '',
        day2: batch.day2 ?? 0,
        schedule2: batch.schedule2 || '',
        day3: batch.day3 ?? 0,
        schedule3: batch.schedule3 || '',
        day4: batch.day4 ?? 0,
        schedule4: batch.schedule4 || '',
        day5: batch.day5 ?? 0,
        schedule5: batch.schedule5 || '',
        day6: batch.day6 ?? 0,
        schedule6: batch.schedule6 || '',
        day7: batch.day7 ?? 0,
        schedule7: batch.schedule7 || '',
        IsActive: batch.IsActive ?? 1,
      });
    } else {
      setFormData({
        batchnum: '',
        totalseat: '30',
        stuff_id: teachers.length > 0 ? teachers[0].id : null,
        startdate: '',
        enddate: '',
        day1: 0,
        schedule1: '',
        day2: 0,
        schedule2: '',
        day3: 0,
        schedule3: '',
        day4: 0,
        schedule4: '',
        day5: 0,
        schedule5: '',
        day6: 0,
        schedule6: '',
        day7: 0,
        schedule7: '',
        IsActive: 1,
      });
    }
    setGeneralError(null);
    setFieldErrors({});
  }, [batch, isOpen, teachers]);

  if (!isOpen) return null;

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    let parsedValue: unknown = value;

    if (name === 'stuff_id' || name === 'IsActive') {
      parsedValue = value === '' ? null : Number(value);
    }

    setFormData((prev) => ({
      ...prev,
      [name]: parsedValue,
    }));

    if (fieldErrors[name]) {
      setFieldErrors((prev) => {
        const copy = { ...prev };
        delete copy[name];
        return copy;
      });
    }
  };

  const handleDayToggle = (dayKey: string, scheduleKey: string, active: boolean) => {
    setFormData((prev) => ({
      ...prev,
      [dayKey]: active ? 1 : 0,
      [scheduleKey]: active ? (prev[scheduleKey as keyof BatchFormData] as string || '09:00 AM - 11:00 AM') : '',
    }));
  };

  const handleScheduleChange = (scheduleKey: string, time: string) => {
    setFormData((prev) => ({
      ...prev,
      [scheduleKey]: time,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setGeneralError(null);
    setFieldErrors({});

    try {
      let saved: Batch;
      if (isEditing && batch) {
        saved = await batchService.updateBatch(batch.id, formData);
      } else {
        saved = await batchService.createBatch(formData);
      }
      onSuccess(saved);
      onClose();
    } catch (err) {
      const apiErr = err as ApiErrorResponse;
      setGeneralError(apiErr.message || 'Failed to save batch. Please verify inputs.');
      if (apiErr.errors && typeof apiErr.errors === 'object') {
        setFieldErrors(apiErr.errors as Record<string, string[]>);
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div className="innova-modal innova-modal-lg" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="innova-modal-header">
          <h2 className="innova-modal-title">
            {isEditing ? `Edit Batch: ${batch?.batchnum}` : 'Create New Academic Batch'}
          </h2>
          <button
            type="button"
            className="innova-btn innova-btn-sm innova-btn-secondary"
            onClick={onClose}
            disabled={submitting}
            aria-label="Close"
          >
            &times;
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}>
          <div className="innova-modal-body">
            {generalError && (
              <Alert type="error" title="Validation Error">
                {generalError}
              </Alert>
            )}

            {/* General Info */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem' }}>
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="batchnum">
                  Batch Number / Code <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <input
                  id="batchnum"
                  name="batchnum"
                  type="text"
                  className="innova-form-control"
                  value={formData.batchnum}
                  onChange={handleChange}
                  placeholder="e.g. WD-2026-01"
                  required
                />
                {fieldErrors.batchnum && <div className="innova-form-error">{fieldErrors.batchnum[0]}</div>}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="totalseat">
                  Total Seats <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <input
                  id="totalseat"
                  name="totalseat"
                  type="text"
                  className="innova-form-control"
                  value={formData.totalseat}
                  onChange={handleChange}
                  placeholder="e.g. 30"
                  required
                />
                {fieldErrors.totalseat && <div className="innova-form-error">{fieldErrors.totalseat[0]}</div>}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="stuff_id">
                  Assigned Teacher / Instructor
                </label>
                <select
                  id="stuff_id"
                  name="stuff_id"
                  className="innova-form-control"
                  value={formData.stuff_id ?? ''}
                  onChange={handleChange}
                >
                  <option value="">-- Select Instructor --</option>
                  {teachers.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name} {t.designation ? `(${t.designation})` : ''}
                    </option>
                  ))}
                </select>
                {fieldErrors.stuff_id && <div className="innova-form-error">{fieldErrors.stuff_id[0]}</div>}
              </div>
            </div>

            {/* Dates & Status */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="startdate">
                  Start Date
                </label>
                <input
                  id="startdate"
                  name="startdate"
                  type="date"
                  className="innova-form-control"
                  value={formData.startdate || ''}
                  onChange={handleChange}
                />
                {fieldErrors.startdate && <div className="innova-form-error">{fieldErrors.startdate[0]}</div>}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="enddate">
                  End Date
                </label>
                <input
                  id="enddate"
                  name="enddate"
                  type="date"
                  className="innova-form-control"
                  value={formData.enddate || ''}
                  onChange={handleChange}
                />
                {fieldErrors.enddate && <div className="innova-form-error">{fieldErrors.enddate[0]}</div>}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="IsActive">
                  Batch Status
                </label>
                <select
                  id="IsActive"
                  name="IsActive"
                  className="innova-form-control"
                  value={formData.IsActive ?? 1}
                  onChange={handleChange}
                >
                  <option value={1}>Running</option>
                  <option value={2}>Upcoming</option>
                  <option value={3}>Completed</option>
                  <option value={0}>Postponed</option>
                </select>
              </div>
            </div>

            {/* Weekly Schedule Matrix */}
            <div style={{ marginTop: '0.75rem', padding: '1rem', background: 'var(--innova-gray-50)', borderRadius: 'var(--innova-radius-md)', border: '1px solid var(--innova-gray-200)' }}>
              <h3 style={{ margin: '0 0 0.75rem 0', fontSize: '0.95rem', fontWeight: 700, color: 'var(--innova-gray-800)' }}>
                Weekly Class Schedule
              </h3>
              <p style={{ margin: '0 0 1rem 0', fontSize: '0.8rem', color: 'var(--innova-gray-500)' }}>
                Select class days and enter session time slots (e.g. <code>09:00 AM - 11:00 AM</code>).
              </p>

              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
                {WEEK_DAYS.map((day) => {
                  const isDayActive = Number(formData[day.key as keyof BatchFormData] ?? 0) === 1;
                  const scheduleVal = (formData[day.scheduleKey as keyof BatchFormData] as string) || '';

                  return (
                    <div
                      key={day.key}
                      style={{
                        display: 'grid',
                        gridTemplateColumns: '120px 90px 1fr',
                        alignItems: 'center',
                        gap: '0.75rem',
                        padding: '0.4rem 0.5rem',
                        borderRadius: 'var(--innova-radius-sm)',
                        backgroundColor: isDayActive ? 'var(--innova-primary-light)' : 'transparent',
                      }}
                    >
                      <span style={{ fontWeight: 600, fontSize: '0.875rem', color: 'var(--innova-gray-800)' }}>
                        {day.label}
                      </span>

                      <div style={{ display: 'flex', gap: '0.5rem', alignItems: 'center' }}>
                        <label style={{ fontSize: '0.8rem', display: 'flex', alignItems: 'center', gap: '0.25rem', cursor: 'pointer' }}>
                          <input
                            type="radio"
                            name={`radio_${day.key}`}
                            checked={isDayActive}
                            onChange={() => handleDayToggle(day.key, day.scheduleKey, true)}
                          />
                          Yes
                        </label>
                        <label style={{ fontSize: '0.8rem', display: 'flex', alignItems: 'center', gap: '0.25rem', cursor: 'pointer' }}>
                          <input
                            type="radio"
                            name={`radio_${day.key}`}
                            checked={!isDayActive}
                            onChange={() => handleDayToggle(day.key, day.scheduleKey, false)}
                          />
                          No
                        </label>
                      </div>

                      <div>
                        {isDayActive ? (
                          <input
                            type="text"
                            className="innova-form-control"
                            style={{ padding: '0.35rem 0.6rem', fontSize: '0.8rem' }}
                            value={scheduleVal}
                            onChange={(e) => handleScheduleChange(day.scheduleKey, e.target.value)}
                            placeholder="e.g. 09:00 AM - 11:00 AM"
                          />
                        ) : (
                          <span style={{ fontSize: '0.8rem', color: 'var(--innova-gray-400)' }}>No class scheduled</span>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>

          {/* Footer Controls */}
          <div className="innova-modal-footer">
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={onClose}
              disabled={submitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="innova-btn innova-btn-primary"
              disabled={submitting}
            >
              {submitting ? (
                <LoadingSpinner size="sm" label="Saving..." />
              ) : isEditing ? (
                'Update Batch'
              ) : (
                'Create Batch'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

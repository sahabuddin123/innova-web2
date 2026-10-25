import React from 'react';
import { Batch, BATCH_STATUS_MAP, WEEK_DAYS } from '../../types';

interface BatchDetailModalProps {
  isOpen: boolean;
  onClose: () => void;
  batch: Batch | null;
  onEdit?: (batch: Batch) => void;
  canEdit?: boolean;
}

export const BatchDetailModal: React.FC<BatchDetailModalProps> = ({
  isOpen,
  onClose,
  batch,
  onEdit,
  canEdit = false,
}) => {
  if (!isOpen || !batch) return null;

  const statusMeta = BATCH_STATUS_MAP[batch.IsActive] || {
    label: 'Unknown',
    badge: 'innova-badge-gray',
    color: '#6b7280',
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div className="innova-modal" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
              <span className={`innova-badge ${statusMeta.badge}`}>
                {statusMeta.label}
              </span>
              <span className="innova-badge innova-badge-gray">
                {batch.totalseat} Seats Capacity
              </span>
            </div>
            <h2 className="innova-modal-title">Batch: {batch.batchnum}</h2>
          </div>
          <button
            type="button"
            className="innova-btn innova-btn-sm innova-btn-secondary"
            onClick={onClose}
            aria-label="Close"
          >
            &times;
          </button>
        </div>

        {/* Modal Body */}
        <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {/* Quick Metrics */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '0.75rem' }}>
            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Total Seats</div>
              <div className="innova-stat-value" style={{ fontSize: '1.2rem', color: 'var(--innova-primary)' }}>
                {batch.totalseat}
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Start Date</div>
              <div className="innova-stat-value" style={{ fontSize: '1rem' }}>
                {batch.startdate ? new Date(batch.startdate).toLocaleDateString() : 'TBD'}
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">End Date</div>
              <div className="innova-stat-value" style={{ fontSize: '1rem' }}>
                {batch.enddate ? new Date(batch.enddate).toLocaleDateString() : 'TBD'}
              </div>
            </div>
          </div>

          {/* Assigned Instructor */}
          <div style={{ padding: '0.85rem 1rem', background: 'var(--innova-gray-50)', borderRadius: 'var(--innova-radius-md)', border: '1px solid var(--innova-gray-200)' }}>
            <div style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--innova-gray-500)', textTransform: 'uppercase', marginBottom: '0.25rem' }}>
              Assigned Teacher / Instructor
            </div>
            {batch.teacher ? (
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <div style={{ fontSize: '1rem', fontWeight: 700, color: 'var(--innova-gray-900)' }}>
                    {batch.teacher.name}
                  </div>
                  {batch.teacher.designation && (
                    <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
                      {batch.teacher.designation}
                    </div>
                  )}
                </div>
                {batch.teacher.phone && (
                  <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
                    📞 {batch.teacher.phone}
                  </div>
                )}
              </div>
            ) : (
              <div style={{ fontSize: '0.9rem', color: 'var(--innova-gray-400)' }}>
                No instructor assigned to this batch.
              </div>
            )}
          </div>

          {/* Weekly Class Schedule */}
          <div>
            <h3 style={{ fontSize: '0.95rem', fontWeight: 700, margin: '0 0 0.5rem 0', color: 'var(--innova-gray-800)' }}>
              Weekly Schedule Details
            </h3>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem' }}>
              {WEEK_DAYS.map((day) => {
                const isDayActive = Number(batch[day.key as keyof Batch] ?? 0) === 1;
                const scheduleVal = (batch[day.scheduleKey as keyof Batch] as string) || '';

                return (
                  <div
                    key={day.key}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      padding: '0.5rem 0.75rem',
                      borderRadius: 'var(--innova-radius-sm)',
                      border: '1px solid var(--innova-gray-200)',
                      backgroundColor: isDayActive ? 'var(--innova-primary-light)' : 'transparent',
                    }}
                  >
                    <span style={{ fontWeight: 600, fontSize: '0.85rem', color: 'var(--innova-gray-800)' }}>
                      {day.label}
                    </span>
                    {isDayActive ? (
                      <span style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--innova-primary)' }}>
                        🕒 {scheduleVal || 'Scheduled'}
                      </span>
                    ) : (
                      <span style={{ fontSize: '0.8rem', color: 'var(--innova-gray-400)' }}>Off Day</span>
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Timestamps */}
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--innova-gray-400)', paddingTop: '0.5rem', borderTop: '1px solid var(--innova-gray-200)' }}>
            <span>Created: {batch.created_at ? new Date(batch.created_at).toLocaleDateString() : 'N/A'}</span>
            <span>Last Updated: {batch.updated_at ? new Date(batch.updated_at).toLocaleDateString() : 'N/A'}</span>
          </div>
        </div>

        {/* Footer */}
        <div className="innova-modal-footer">
          <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
            Close
          </button>
          {canEdit && onEdit && (
            <button
              type="button"
              className="innova-btn innova-btn-primary"
              onClick={() => {
                onClose();
                onEdit(batch);
              }}
            >
              Edit Batch
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

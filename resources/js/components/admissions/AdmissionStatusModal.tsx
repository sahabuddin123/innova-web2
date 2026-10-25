import React, { useState } from 'react';
import { Admission, ADMISSION_STATUS_MAP } from '../../types';
import { admissionService } from '../../services';
import { Alert } from '../common';

interface AdmissionStatusModalProps {
  admission: Admission | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (updated: Admission) => void;
}

export const AdmissionStatusModal: React.FC<AdmissionStatusModalProps> = ({
  admission,
  isOpen,
  onClose,
  onSuccess,
}) => {
  if (!isOpen || !admission) return null;

  const [selectedStatus, setSelectedStatus] = useState<number>(admission.isActive);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const updated = await admissionService.updateStatus(admission.id, selectedStatus);
      onSuccess(updated);
      onClose();
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setError(apiErr?.response?.data?.message || apiErr?.message || 'Failed to update admission status');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div className="innova-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '540px' }}>
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Online Admission Lifecycle
            </div>
            <h2 className="innova-modal-title">Update Status — {admission.username}</h2>
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

        {/* Form Body */}
        <form onSubmit={handleSubmit}>
          <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {error && (
              <Alert type="error" title="Status Update Error">
                {error}
              </Alert>
            )}

            <p style={{ margin: 0, fontSize: '0.875rem', color: 'var(--innova-gray-600)' }}>
              Select the new inquiry lifecycle state for <strong>{admission.username}</strong> (Inquiry #{admission.id}):
            </p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
              {Object.values(ADMISSION_STATUS_MAP).map((meta) => {
                const isSelected = selectedStatus === meta.code;
                return (
                  <label
                    key={meta.code}
                    style={{
                      display: 'flex',
                      alignItems: 'flex-start',
                      gap: '0.75rem',
                      padding: '0.75rem 1rem',
                      borderRadius: 'var(--innova-radius-md)',
                      border: isSelected
                        ? '2px solid var(--innova-primary-600)'
                        : '1px solid var(--innova-gray-200)',
                      backgroundColor: isSelected
                        ? 'var(--innova-primary-50, #f0fdf4)'
                        : 'var(--innova-card-bg)',
                      cursor: 'pointer',
                      transition: 'all 0.15s ease',
                    }}
                  >
                    <input
                      type="radio"
                      name="admission_status"
                      value={meta.code}
                      checked={isSelected}
                      onChange={() => setSelectedStatus(meta.code)}
                      style={{ marginTop: '0.2rem' }}
                    />
                    <div style={{ flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        <span style={{ fontWeight: 600, color: 'var(--innova-gray-900)', fontSize: '0.9rem' }}>
                          {meta.label}
                        </span>
                        <span className={`innova-badge ${meta.badgeClass}`} style={{ fontSize: '0.7rem' }}>
                          Code {meta.code}
                        </span>
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', marginTop: '0.15rem' }}>
                        {meta.description}
                      </div>
                    </div>
                  </label>
                );
              })}
            </div>
          </div>

          {/* Footer */}
          <div className="innova-modal-footer">
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={onClose}
              disabled={isSubmitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="innova-btn innova-btn-primary"
              disabled={isSubmitting || selectedStatus === admission.isActive}
            >
              {isSubmitting ? 'Saving Status...' : 'Save New Status'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

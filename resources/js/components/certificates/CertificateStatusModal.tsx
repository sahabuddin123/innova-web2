import React, { useState } from 'react';
import { CertificateIcbd, ICBD_STATUS_MAP } from '../../types';
import { certificateService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface CertificateStatusModalProps {
  certificate: CertificateIcbd | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (updated: CertificateIcbd) => void;
}

export const CertificateStatusModal: React.FC<CertificateStatusModalProps> = ({
  certificate,
  isOpen,
  onClose,
  onSuccess,
}) => {
  if (!isOpen || !certificate) return null;

  const [selectedStatus, setSelectedStatus] = useState<number>(certificate.is_active);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const updated = await certificateService.updateIcbdStatus(certificate.id, selectedStatus);
      onSuccess(updated);
      onClose();
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        apiErr?.response?.data?.message || apiErr?.message || 'Failed to update certificate status'
      );
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
              Certificate Lifecycle Transition
            </div>
            <h2 className="innova-modal-title">Status — {certificate.certificate_no}</h2>
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
              Select the new lifecycle state for certificate <strong>{certificate.certificate_no}</strong> (Candidate: <strong>{certificate.student?.sname || `#${certificate.student_id}`}</strong>):
            </p>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
              {Object.values(ICBD_STATUS_MAP).map((meta) => {
                const isSelected = selectedStatus === meta.code;
                return (
                  <label
                    key={meta.code}
                    style={{
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.75rem',
                      padding: '0.75rem 1rem',
                      borderRadius: '8px',
                      border: isSelected ? '2px solid var(--innova-primary)' : '1px solid var(--innova-gray-200)',
                      background: isSelected ? 'rgba(79, 70, 229, 0.04)' : '#fff',
                      cursor: 'pointer',
                      transition: 'all 0.15s ease',
                    }}
                  >
                    <input
                      type="radio"
                      name="certificate_status"
                      value={meta.code}
                      checked={isSelected}
                      onChange={() => setSelectedStatus(meta.code)}
                      style={{ accentColor: 'var(--innova-primary)' }}
                    />
                    <div style={{ flex: 1 }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                        <span style={{ fontWeight: 600, fontSize: '0.9rem', color: 'var(--innova-gray-900)' }}>
                          {meta.label}
                        </span>
                        <span className={`innova-badge ${meta.badgeClass}`}>{meta.label}</span>
                      </div>
                      <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', marginTop: '0.15rem' }}>
                        {meta.code === 1 && 'Official certificate delivered to student or authorized receiver.'}
                        {meta.code === 2 && 'Certificate generated, awaiting administrative signoff or printing.'}
                        {meta.code === 3 && 'Queued for physical security printing on institutional parchment.'}
                        {meta.code === 4 && 'Revoked due to disciplinary, credential invalidation, or record error.'}
                      </div>
                    </div>
                  </label>
                );
              })}
            </div>

            {selectedStatus === 4 && (
              <Alert type="warning" title="Revocation Notice">
                Marking this certificate as <strong>Revoked</strong> will immediately invalidate public verification checks and revert student certification registration if no other active certificate exists.
              </Alert>
            )}
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
              disabled={isSubmitting || selectedStatus === certificate.is_active}
            >
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" />
                  <span>Updating...</span>
                </>
              ) : (
                'Save Status'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

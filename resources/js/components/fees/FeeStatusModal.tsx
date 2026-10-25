import React, { useState, useEffect } from 'react';
import { FeeTransaction } from '../../types';
import { feeService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface FeeStatusModalProps {
  isOpen: boolean;
  onClose: () => void;
  transaction: FeeTransaction | null;
  onSuccess: (updated: FeeTransaction) => void;
}

export const FeeStatusModal: React.FC<FeeStatusModalProps> = ({
  isOpen,
  onClose,
  transaction,
  onSuccess,
}) => {
  const [selectedStatus, setSelectedStatus] = useState<number>(0);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen && transaction) {
      setSelectedStatus(Number(transaction.status) ?? 0);
      setError(null);
    }
  }, [isOpen, transaction]);

  if (!isOpen || !transaction) return null;

  const currentStatus = Number(transaction.status) ?? 0;
  const isChanging = selectedStatus !== currentStatus;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!isChanging) {
      onClose();
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      const res = await feeService.updateStatus(transaction.id, selectedStatus);
      onSuccess(res.transaction);
      onClose();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Failed to update transaction status.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose}>
      <div
        className="innova-modal-content"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '520px' }}
      >
        <div className="innova-modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(234, 179, 8, 0.1)',
                color: 'var(--color-warning-600, #d97706)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
            </div>
            <div>
              <h2 className="innova-modal-title" style={{ margin: 0 }}>
                Update Payment Status
              </h2>
              <p style={{ margin: '2px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                Transaction #{transaction.id} — ৳{Number(transaction.paidamount).toLocaleString()}
              </p>
            </div>
          </div>
          <button type="button" className="innova-modal-close" onClick={onClose} aria-label="Close">
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="innova-modal-body">
            {error && (
              <Alert type="error">
                {error}
              </Alert>
            )}

            <div style={{ marginBottom: '16px' }}>
              <label className="innova-label" style={{ fontWeight: 600 }}>
                Approval Workflow State
              </label>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                <label
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px',
                    padding: '12px 14px',
                    borderRadius: '8px',
                    border: `1.5px solid ${selectedStatus === 0 ? 'var(--color-success-600, #16a34a)' : 'var(--color-border)'}`,
                    background: selectedStatus === 0 ? 'rgba(34, 197, 94, 0.05)' : '#fff',
                    cursor: 'pointer',
                  }}
                >
                  <input
                    type="radio"
                    name="statusOption"
                    value={0}
                    checked={selectedStatus === 0}
                    onChange={() => setSelectedStatus(0)}
                  />
                  <div>
                    <div style={{ fontWeight: 600, color: 'var(--color-success-700, #15803d)' }}>
                      Approved (Active in Ledger)
                    </div>
                    <div style={{ fontSize: '12px', color: 'var(--color-text-muted)' }}>
                      Amount counts towards candidate's paid balance and reduces dues. Dispatches confirmation SMS.
                    </div>
                  </div>
                </label>

                <label
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px',
                    padding: '12px 14px',
                    borderRadius: '8px',
                    border: `1.5px solid ${selectedStatus === 1 ? 'var(--color-warning-600, #d97706)' : 'var(--color-border)'}`,
                    background: selectedStatus === 1 ? 'rgba(234, 179, 8, 0.05)' : '#fff',
                    cursor: 'pointer',
                  }}
                >
                  <input
                    type="radio"
                    name="statusOption"
                    value={1}
                    checked={selectedStatus === 1}
                    onChange={() => setSelectedStatus(1)}
                  />
                  <div>
                    <div style={{ fontWeight: 600, color: 'var(--color-warning-700, #b45309)' }}>
                      Pending Approval (On Hold)
                    </div>
                    <div style={{ fontSize: '12px', color: 'var(--color-text-muted)' }}>
                      Excluded from student's approved paid total until reviewed and confirmed by accounts.
                    </div>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <div className="innova-modal-footer">
            <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </button>
            <button
              type="submit"
              className="innova-btn innova-btn-primary"
              disabled={isSubmitting || !isChanging}
            >
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" /> Updating Status...
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

import React, { useState, useEffect } from 'react';
import { FeeTransaction, UpdateFeeTransactionPayload } from '../../types';
import { feeService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface FeeTransactionEditModalProps {
  isOpen: boolean;
  onClose: () => void;
  transaction: FeeTransaction | null;
  onSuccess: (updated: FeeTransaction) => void;
}

export const FeeTransactionEditModal: React.FC<FeeTransactionEditModalProps> = ({
  isOpen,
  onClose,
  transaction,
  onSuccess,
}) => {
  const [paidAmount, setPaidAmount] = useState<string>('');
  const [paymentType, setPaymentType] = useState<number>(1);
  const [paidDate, setPaidDate] = useState<string>('');
  const [paidRemark, setPaidRemark] = useState<string>('');

  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const isLockedGateway = Boolean(
    transaction && Number(transaction.channel) === 2 && transaction.trxID
  );

  useEffect(() => {
    if (isOpen && transaction) {
      setPaidAmount(String(transaction.paidamount));
      setPaymentType(Number(transaction.paymentType) || 1);
      setPaidDate(transaction.paiddate || '');
      setPaidRemark(transaction.paidremark || '');
      setError(null);
      setFieldErrors({});
    }
  }, [isOpen, transaction]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!transaction) return;

    const numAmount = parseFloat(paidAmount);
    if (isNaN(numAmount) || numAmount <= 0) {
      setError('Please enter a valid positive payment amount.');
      return;
    }

    if (!paidRemark.trim()) {
      setError('Payment remarks are required.');
      return;
    }

    setIsSubmitting(true);
    setError(null);
    setFieldErrors({});

    const payload: UpdateFeeTransactionPayload = {
      paidamount: numAmount.toFixed(2),
      paymentType,
      paiddate: paidDate,
      paidremark: paidRemark.trim(),
    };

    try {
      const updated = await feeService.updateTransaction(transaction.id, payload);
      onSuccess(updated);
      onClose();
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      setError(errorObj.message || 'Failed to update transaction.');
      if (errorObj.errors) {
        setFieldErrors(errorObj.errors);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen || !transaction) return null;

  return (
    <div className="innova-modal-backdrop" onClick={onClose}>
      <div
        className="innova-modal-content"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '560px' }}
      >
        <div className="innova-modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(59, 130, 246, 0.1)',
                color: 'var(--color-primary-600, #2563eb)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M12 20h9" />
                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
              </svg>
            </div>
            <div>
              <h2 className="innova-modal-title" style={{ margin: 0 }}>
                Edit Transaction #{transaction.id}
              </h2>
              <p style={{ margin: '2px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                Candidate: {transaction.student?.sname || `Student #${transaction.student_id}`}
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

            {isLockedGateway && (
              <div
                style={{
                  background: 'rgba(234, 179, 8, 0.1)',
                  border: '1px solid rgba(234, 179, 8, 0.3)',
                  borderRadius: '8px',
                  padding: '12px',
                  fontSize: '13px',
                  color: 'var(--color-warning-800, #854d0e)',
                  marginBottom: '16px',
                }}
              >
                <strong>Audit Protection:</strong> The paid amount is locked because this transaction was verified through
                the bKash Payment Gateway (TrxID: {transaction.trxID}).
              </div>
            )}

            <div style={{ marginBottom: '14px' }}>
              <label className="innova-label" htmlFor="edit-fee-amount">
                Paid Amount (BDT ৳) <span style={{ color: 'var(--color-error-600)' }}>*</span>
              </label>
              <input
                id="edit-fee-amount"
                type="number"
                step="0.01"
                min="1"
                className={`innova-input ${fieldErrors.paidamount ? 'innova-input-error' : ''}`}
                value={paidAmount}
                onChange={(e) => setPaidAmount(e.target.value)}
                disabled={isLockedGateway}
                required
              />
              {fieldErrors.paidamount && (
                <span style={{ fontSize: '12px', color: 'var(--color-error-600)' }}>
                  {fieldErrors.paidamount[0]}
                </span>
              )}
            </div>

            <div style={{ marginBottom: '14px' }}>
              <label className="innova-label" htmlFor="edit-fee-type">
                Payment Type <span style={{ color: 'var(--color-error-600)' }}>*</span>
              </label>
              <select
                id="edit-fee-type"
                className="innova-input"
                value={paymentType}
                onChange={(e) => setPaymentType(Number(e.target.value))}
              >
                <option value={1}>Course Fee (Deducts from Dues)</option>
                <option value={2}>Registration Fee</option>
              </select>
            </div>

            <div style={{ marginBottom: '14px' }}>
              <label className="innova-label" htmlFor="edit-fee-date">
                Payment Date <span style={{ color: 'var(--color-error-600)' }}>*</span>
              </label>
              <input
                id="edit-fee-date"
                type="date"
                className={`innova-input ${fieldErrors.paiddate ? 'innova-input-error' : ''}`}
                value={paidDate}
                onChange={(e) => setPaidDate(e.target.value)}
                required
              />
              {fieldErrors.paiddate && (
                <span style={{ fontSize: '12px', color: 'var(--color-error-600)' }}>
                  {fieldErrors.paiddate[0]}
                </span>
              )}
            </div>

            <div style={{ marginBottom: '14px' }}>
              <label className="innova-label" htmlFor="edit-fee-remark">
                Remarks / Payment Notes <span style={{ color: 'var(--color-error-600)' }}>*</span>
              </label>
              <input
                id="edit-fee-remark"
                type="text"
                className={`innova-input ${fieldErrors.paidremark ? 'innova-input-error' : ''}`}
                value={paidRemark}
                onChange={(e) => setPaidRemark(e.target.value)}
                required
              />
              {fieldErrors.paidremark && (
                <span style={{ fontSize: '12px', color: 'var(--color-error-600)' }}>
                  {fieldErrors.paidremark[0]}
                </span>
              )}
            </div>
          </div>

          <div className="innova-modal-footer">
            <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </button>
            <button type="submit" className="innova-btn innova-btn-primary" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" /> Updating...
                </>
              ) : (
                'Save Changes'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

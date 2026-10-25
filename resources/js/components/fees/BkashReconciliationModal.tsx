import React, { useState } from 'react';
import { BkashQueryResult } from '../../types';
import { feeService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface BkashReconciliationModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export const BkashReconciliationModal: React.FC<BkashReconciliationModalProps> = ({
  isOpen,
  onClose,
}) => {
  const [activeMode, setActiveMode] = useState<'payment' | 'trx'>('trx');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [result, setResult] = useState<BkashQueryResult | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  if (!isOpen) return null;

  const handleQuery = async (e: React.FormEvent) => {
    e.preventDefault();
    const query = searchQuery.trim();
    if (!query) {
      setError('Please enter a Payment ID or Transaction ID to verify with bKash.');
      return;
    }

    setIsLoading(true);
    setError(null);
    setResult(null);

    try {
      if (activeMode === 'payment') {
        const data = await feeService.queryBkashPayment(query);
        setResult(data);
      } else {
        const data = await feeService.searchBkashTrx(query);
        setResult(data);
      }
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gateway verification query failed.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose}>
      <div
        className="innova-modal-content"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '600px' }}
      >
        <div className="innova-modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(236, 72, 153, 0.1)',
                color: '#db2777',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 6v6l4 2" />
              </svg>
            </div>
            <div>
              <h2 className="innova-modal-title" style={{ margin: 0 }}>
                bKash Gateway Reconciliation
              </h2>
              <p style={{ margin: '2px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                Verify online student payment status directly with bKash Checkout servers
              </p>
            </div>
          </div>
          <button type="button" className="innova-modal-close" onClick={onClose} aria-label="Close">
            &times;
          </button>
        </div>

        <div className="innova-modal-body">
          {/* Mode Switcher */}
          <div
            style={{
              display: 'flex',
              background: 'var(--color-bg-subtle, #f1f5f9)',
              padding: '4px',
              borderRadius: '8px',
              marginBottom: '16px',
            }}
          >
            <button
              type="button"
              className={`innova-btn innova-btn-sm ${activeMode === 'trx' ? 'innova-btn-primary' : 'innova-btn-ghost'}`}
              style={{ flex: 1 }}
              onClick={() => {
                setActiveMode('trx');
                setResult(null);
                setError(null);
              }}
            >
              Search by Transaction ID (trxID)
            </button>
            <button
              type="button"
              className={`innova-btn innova-btn-sm ${activeMode === 'payment' ? 'innova-btn-primary' : 'innova-btn-ghost'}`}
              style={{ flex: 1 }}
              onClick={() => {
                setActiveMode('payment');
                setResult(null);
                setError(null);
              }}
            >
              Query by Payment ID
            </button>
          </div>

          <form onSubmit={handleQuery} style={{ marginBottom: '18px' }}>
            <div style={{ display: 'flex', gap: '8px' }}>
              <input
                type="text"
                className="innova-input"
                placeholder={
                  activeMode === 'trx'
                    ? 'Enter 10-digit bKash TrxID (e.g. 9K72B3XZ4M)...'
                    : 'Enter checkout session Payment ID...'
                }
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                autoFocus
              />
              <button type="submit" className="innova-btn innova-btn-primary" disabled={isLoading}>
                {isLoading ? <LoadingSpinner size="sm" /> : 'Verify'}
              </button>
            </div>
          </form>

          {error && (
            <Alert type="error">
              {error}
            </Alert>
          )}

          {result && (
            <div
              style={{
                border: '1px solid #fbcfe8',
                background: '#fdf2f8',
                borderRadius: '8px',
                padding: '16px',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                <span style={{ fontSize: '14px', fontWeight: 700, color: '#be185d' }}>
                  Gateway Response Details
                </span>
                <span
                  style={{
                    padding: '2px 8px',
                    borderRadius: '4px',
                    fontSize: '12px',
                    fontWeight: 700,
                    background: result.transactionStatus === 'Completed' ? '#dcfce7' : '#fef9c3',
                    color: result.transactionStatus === 'Completed' ? '#15803d' : '#854d0e',
                  }}
                >
                  {result.transactionStatus || 'Verified'}
                </span>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px', fontSize: '13px' }}>
                <div>
                  <span style={{ color: 'var(--color-text-muted)' }}>Amount:</span>
                  <div style={{ fontWeight: 700, fontSize: '15px', color: '#0f172a' }}>
                    ৳{Number(result.amount || 0).toLocaleString()} {result.currency || 'BDT'}
                  </div>
                </div>

                <div>
                  <span style={{ color: 'var(--color-text-muted)' }}>Customer Mobile:</span>
                  <div style={{ fontWeight: 600 }}>{result.customerMsisdn || 'N/A'}</div>
                </div>

                <div>
                  <span style={{ color: 'var(--color-text-muted)' }}>Transaction ID:</span>
                  <div style={{ fontFamily: 'monospace', fontWeight: 700 }}>{result.trxID || 'N/A'}</div>
                </div>

                <div>
                  <span style={{ color: 'var(--color-text-muted)' }}>Payment ID:</span>
                  <div style={{ fontFamily: 'monospace', fontSize: '12px' }}>{result.paymentID || 'N/A'}</div>
                </div>

                {result.paymentExecuteTime && (
                  <div style={{ gridColumn: 'span 2' }}>
                    <span style={{ color: 'var(--color-text-muted)' }}>Gateway Execution Time:</span>
                    <div>{result.paymentExecuteTime}</div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        <div className="innova-modal-footer">
          <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

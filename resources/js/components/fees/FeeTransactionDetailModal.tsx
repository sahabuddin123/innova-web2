import React from 'react';
import { FeeTransaction, FEE_STATUS_MAP, FEE_CHANNEL_MAP, FEE_TYPE_MAP } from '../../types';

interface FeeTransactionDetailModalProps {
  isOpen: boolean;
  onClose: () => void;
  transaction: FeeTransaction | null;
  onOpenReceipt: (id: number) => void;
  onEdit?: (trx: FeeTransaction) => void;
  onStatusChange?: (trx: FeeTransaction) => void;
}

export const FeeTransactionDetailModal: React.FC<FeeTransactionDetailModalProps> = ({
  isOpen,
  onClose,
  transaction,
  onOpenReceipt,
  onEdit,
  onStatusChange,
}) => {
  if (!isOpen || !transaction) return null;

  const statusConfig = FEE_STATUS_MAP[Number(transaction.status) ?? 0] || {
    label: 'Unknown',
    badgeClass: 'innova-badge-neutral',
  };

  const channelConfig = FEE_CHANNEL_MAP[Number(transaction.channel) ?? 1] || {
    label: 'Counter',
    badgeClass: 'innova-badge-neutral',
  };

  const paymentTypeName = FEE_TYPE_MAP[Number(transaction.paymentType) ?? 1] || 'Course Fee';
  const isBkash = Number(transaction.channel) === 2;

  return (
    <div className="innova-modal-backdrop" onClick={onClose}>
      <div
        className="innova-modal-content"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '640px' }}
      >
        <div className="innova-modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(99, 102, 241, 0.1)',
                color: 'var(--color-primary-600, #4f46e5)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="2" y="4" width="20" height="16" rx="2" />
                <line x1="2" y1="10" x2="22" y2="10" />
              </svg>
            </div>
            <div>
              <h2 className="innova-modal-title" style={{ margin: 0 }}>
                Transaction #{transaction.id}
              </h2>
              <div style={{ display: 'flex', gap: '6px', marginTop: '4px' }}>
                <span className={`innova-badge ${channelConfig.badgeClass}`}>{channelConfig.label}</span>
                <span className={`innova-badge ${statusConfig.badgeClass}`}>{statusConfig.label}</span>
                <span className="innova-badge innova-badge-neutral">{paymentTypeName}</span>
              </div>
            </div>
          </div>
          <button type="button" className="innova-modal-close" onClick={onClose} aria-label="Close">
            &times;
          </button>
        </div>

        <div className="innova-modal-body">
          {/* Main Amount Card */}
          <div
            style={{
              background: 'linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%)',
              border: '1px solid var(--color-border)',
              borderRadius: '10px',
              padding: '18px',
              textAlign: 'center',
              marginBottom: '20px',
            }}
          >
            <div style={{ fontSize: '12px', textTransform: 'uppercase', color: 'var(--color-text-muted)', fontWeight: 600 }}>
              Transaction Amount
            </div>
            <div style={{ fontSize: '32px', fontWeight: 800, color: 'var(--color-success-700, #15803d)', marginTop: '4px' }}>
              ৳{Number(transaction.paidamount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
            </div>
            <div style={{ fontSize: '13px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
              Recorded Date: <strong>{transaction.paiddate}</strong>
            </div>
          </div>

          {/* Student Association */}
          <div
            style={{
              border: '1px solid var(--color-border)',
              borderRadius: '8px',
              padding: '14px 16px',
              marginBottom: '18px',
            }}
          >
            <div style={{ fontSize: '11px', textTransform: 'uppercase', color: 'var(--color-text-muted)', fontWeight: 700, marginBottom: '6px' }}>
              Associated Student Record
            </div>
            <div style={{ fontSize: '16px', fontWeight: 700, color: 'var(--color-text-main)' }}>
              {transaction.student?.sname || `Student #${transaction.student_id}`}
            </div>
            <div style={{ fontSize: '13px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
              Phone: {transaction.student?.phone || 'N/A'}
            </div>
            {transaction.student?.course && (
              <div style={{ fontSize: '13px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
                Course: <strong>{transaction.student.course.name}</strong>
              </div>
            )}
            {transaction.student?.batch && (
              <div style={{ fontSize: '13px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
                Batch: <strong>{transaction.student.batch.batchnum}</strong>
              </div>
            )}
          </div>

          {/* Transaction Metadata Grid */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: '1fr 1fr',
              gap: '12px',
              fontSize: '13px',
              marginBottom: '18px',
            }}
          >
            <div style={{ padding: '10px 12px', background: 'var(--color-bg-subtle, #f8fafc)', borderRadius: '6px' }}>
              <div style={{ color: 'var(--color-text-muted)', fontSize: '11px' }}>Payment Channel</div>
              <div style={{ fontWeight: 600, marginTop: '2px' }}>{channelConfig.label}</div>
            </div>
            <div style={{ padding: '10px 12px', background: 'var(--color-bg-subtle, #f8fafc)', borderRadius: '6px' }}>
              <div style={{ color: 'var(--color-text-muted)', fontSize: '11px' }}>Payment Type</div>
              <div style={{ fontWeight: 600, marginTop: '2px' }}>{paymentTypeName}</div>
            </div>
            <div style={{ padding: '10px 12px', background: 'var(--color-bg-subtle, #f8fafc)', borderRadius: '6px' }}>
              <div style={{ color: 'var(--color-text-muted)', fontSize: '11px' }}>Recorded By / Operator</div>
              <div style={{ fontWeight: 600, marginTop: '2px' }}>{transaction.user?.name || 'Administrator'}</div>
            </div>
            <div style={{ padding: '10px 12px', background: 'var(--color-bg-subtle, #f8fafc)', borderRadius: '6px' }}>
              <div style={{ color: 'var(--color-text-muted)', fontSize: '11px' }}>Created Timestamp</div>
              <div style={{ fontWeight: 600, marginTop: '2px' }}>
                {transaction.created_at ? new Date(transaction.created_at).toLocaleString() : 'N/A'}
              </div>
            </div>
          </div>

          {/* Gateway Information */}
          {isBkash && (
            <div
              style={{
                background: 'rgba(236, 72, 153, 0.08)',
                border: '1px solid rgba(236, 72, 153, 0.2)',
                borderRadius: '8px',
                padding: '12px 14px',
                fontSize: '13px',
                marginBottom: '16px',
              }}
            >
              <div style={{ fontWeight: 700, color: '#be185d', marginBottom: '4px' }}>bKash Gateway Verification</div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '2px' }}>
                <span style={{ color: 'var(--color-text-muted)' }}>Gateway TrxID:</span>
                <span style={{ fontFamily: 'monospace', fontWeight: 700 }}>{transaction.trxID || 'N/A'}</span>
              </div>
              {transaction.paymentID && (
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '2px' }}>
                  <span style={{ color: 'var(--color-text-muted)' }}>Checkout Session:</span>
                  <span style={{ fontFamily: 'monospace', fontSize: '12px' }}>{transaction.paymentID}</span>
                </div>
              )}
            </div>
          )}

          {/* Remarks */}
          <div style={{ fontSize: '13px', color: 'var(--color-text-main)' }}>
            <span style={{ color: 'var(--color-text-muted)', fontWeight: 600 }}>Remarks: </span>
            {transaction.paidremark || 'No remarks recorded.'}
          </div>
        </div>

        <div className="innova-modal-footer" style={{ display: 'flex', justifyContent: 'space-between' }}>
          <div style={{ display: 'flex', gap: '8px' }}>
            {onStatusChange && (
              <button
                type="button"
                className="innova-btn innova-btn-secondary innova-btn-sm"
                onClick={() => {
                  onClose();
                  onStatusChange(transaction);
                }}
              >
                Change Status
              </button>
            )}
            {onEdit && (
              <button
                type="button"
                className="innova-btn innova-btn-secondary innova-btn-sm"
                onClick={() => {
                  onClose();
                  onEdit(transaction);
                }}
              >
                Edit
              </button>
            )}
          </div>

          <div style={{ display: 'flex', gap: '8px' }}>
            <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
              Close
            </button>
            <button
              type="button"
              className="innova-btn innova-btn-primary"
              onClick={() => {
                onClose();
                onOpenReceipt(transaction.id);
              }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <polyline points="6 9 6 2 18 2 18 9" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <rect x="6" y="14" width="12" height="8" />
              </svg>
              View Receipt / Payslip
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

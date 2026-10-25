import React, { useState, useEffect } from 'react';
import { StudentFeeBalanceResponse, FEE_STATUS_MAP, FEE_CHANNEL_MAP, FEE_TYPE_MAP } from '../../types';
import { feeService } from '../../services';
import { LoadingSpinner, Alert } from '../common';

interface StudentLedgerCardProps {
  studentId: number | null;
  onCollectFee?: (studentId: number) => void;
  onViewReceipt?: (trxId: number) => void;
}

export const StudentLedgerCard: React.FC<StudentLedgerCardProps> = ({
  studentId,
  onCollectFee,
  onViewReceipt,
}) => {
  const [balance, setBalance] = useState<StudentFeeBalanceResponse | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!studentId) {
      setBalance(null);
      return;
    }

    setIsLoading(true);
    setError(null);

    feeService
      .getStudentBalance(studentId)
      .then((data) => setBalance(data))
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to retrieve student fee ledger.');
      })
      .finally(() => setIsLoading(false));
  }, [studentId]);

  if (!studentId) {
    return (
      <div
        style={{
          padding: '36px',
          textAlign: 'center',
          color: 'var(--color-text-muted)',
          background: 'var(--color-bg-card, #fff)',
          borderRadius: '10px',
          border: '1px dashed var(--color-border)',
        }}
      >
        <svg
          width="40"
          height="40"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="1.5"
          style={{ margin: '0 auto 8px', display: 'block', opacity: 0.5 }}
        >
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        Select a candidate from the directory or search below to inspect their full payment ledger.
      </div>
    );
  }

  if (isLoading) {
    return (
      <div style={{ padding: '48px', textAlign: 'center' }}>
        <LoadingSpinner />
        <div style={{ marginTop: '12px', fontSize: '13px', color: 'var(--color-text-muted)' }}>
          Loading candidate fee ledger & transactions...
        </div>
      </div>
    );
  }

  if (error || !balance) {
    return (
      <Alert type="error">
        {error || 'Unable to load ledger record.'}
      </Alert>
    );
  }

  const { student, account_summary, transactions } = balance;
  const isDue = account_summary.due_course_fee > 0;

  return (
    <div
      style={{
        background: 'var(--color-bg-card, #fff)',
        borderRadius: '12px',
        border: '1px solid var(--color-border)',
        boxShadow: 'var(--shadow-sm)',
        overflow: 'hidden',
      }}
    >
      {/* Header & Candidate Identity */}
      <div
        style={{
          padding: '18px 20px',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '12px',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
          <div
            style={{
              width: '44px',
              height: '44px',
              borderRadius: '50%',
              background: 'var(--color-primary-100, #e0e7ff)',
              color: 'var(--color-primary-700, #4338ca)',
              fontWeight: 800,
              fontSize: '18px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            {student.name.charAt(0).toUpperCase()}
          </div>
          <div>
            <div style={{ fontSize: '18px', fontWeight: 700, color: 'var(--color-text-main)' }}>
              {student.name}
            </div>
            <div style={{ fontSize: '13px', color: 'var(--color-text-muted)' }}>
              Phone: {student.phone} | Course: <strong>{student.course_name || 'Enrolled Course'}</strong> (
              {student.batch_num || 'Batch Assigned'})
            </div>
          </div>
        </div>

        {onCollectFee && (
          <button
            type="button"
            className="innova-btn innova-btn-primary"
            onClick={() => onCollectFee(student.id)}
            style={{ background: 'var(--color-success-600, #16a34a)', borderColor: 'var(--color-success-600, #16a34a)' }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Collect Payment
          </button>
        )}
      </div>

      {/* Account Metric Grid */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(140px, 1fr))',
          gap: '12px',
          padding: '16px 20px',
          background: 'var(--color-bg-subtle, #f8fafc)',
          borderBottom: '1px solid var(--color-border)',
        }}
      >
        <div style={{ padding: '10px 14px', background: '#fff', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
          <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>Course Fee</div>
          <div style={{ fontSize: '16px', fontWeight: 700, marginTop: '2px' }}>
            ৳{Number(account_summary.total_course_fee).toLocaleString()}
          </div>
        </div>

        <div style={{ padding: '10px 14px', background: '#fff', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
          <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>Discount</div>
          <div style={{ fontSize: '16px', fontWeight: 700, color: 'var(--color-primary-600, #4f46e5)', marginTop: '2px' }}>
            ৳{Number(account_summary.discount).toLocaleString()}
          </div>
        </div>

        <div style={{ padding: '10px 14px', background: '#fff', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
          <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>Net Payable</div>
          <div style={{ fontSize: '16px', fontWeight: 700, marginTop: '2px' }}>
            ৳{Number(account_summary.net_payable).toLocaleString()}
          </div>
        </div>

        <div style={{ padding: '10px 14px', background: '#fff', borderRadius: '8px', border: '1px solid var(--color-border)' }}>
          <div style={{ fontSize: '11px', color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>Course Paid</div>
          <div style={{ fontSize: '16px', fontWeight: 700, color: 'var(--color-success-600, #16a34a)', marginTop: '2px' }}>
            ৳{Number(account_summary.total_paid_course).toLocaleString()}
          </div>
        </div>

        <div
          style={{
            padding: '10px 14px',
            background: isDue ? 'rgba(239, 68, 68, 0.08)' : 'rgba(34, 197, 94, 0.08)',
            borderRadius: '8px',
            border: `1.5px solid ${isDue ? 'rgba(239, 68, 68, 0.25)' : 'rgba(34, 197, 94, 0.25)'}`,
          }}
        >
          <div style={{ fontSize: '11px', color: isDue ? 'var(--color-error-600)' : 'var(--color-success-600)', fontWeight: 700, textTransform: 'uppercase' }}>
            Current Balance Due
          </div>
          <div style={{ fontSize: '18px', fontWeight: 800, color: isDue ? 'var(--color-error-700)' : 'var(--color-success-700)', marginTop: '2px' }}>
            ৳{Number(account_summary.due_course_fee).toLocaleString()}
          </div>
        </div>
      </div>

      {/* Transaction History Table */}
      <div style={{ padding: '16px 20px' }}>
        <div style={{ fontSize: '14px', fontWeight: 700, color: 'var(--color-text-main)', marginBottom: '12px' }}>
          Payment History ({transactions.length} record{transactions.length === 1 ? '' : 's'})
        </div>

        {transactions.length === 0 ? (
          <div style={{ padding: '20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: '13px' }}>
            No payments have been recorded for this student yet.
          </div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table className="innova-table" style={{ width: '100%', fontSize: '13px' }}>
              <thead>
                <tr>
                  <th>Tnx ID</th>
                  <th>Date</th>
                  <th>Type</th>
                  <th>Channel</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Remarks / TrxID</th>
                  <th style={{ textAlign: 'right' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((trx) => {
                  const statusInfo = FEE_STATUS_MAP[Number(trx.status) ?? 0] || {
                    label: 'Approved',
                    badgeClass: 'innova-badge-success',
                  };
                  const channelInfo = FEE_CHANNEL_MAP[Number(trx.channel) ?? 1] || {
                    label: 'Cash',
                    badgeClass: 'innova-badge-neutral',
                  };
                  const typeLabel = FEE_TYPE_MAP[Number(trx.paymentType) ?? 1] || 'Course Fee';

                  return (
                    <tr key={trx.id}>
                      <td style={{ fontWeight: 700 }}>#{trx.id}</td>
                      <td>{trx.paiddate}</td>
                      <td>
                        <span className="innova-badge innova-badge-neutral">{typeLabel}</span>
                      </td>
                      <td>
                        <span className={`innova-badge ${channelInfo.badgeClass}`}>{channelInfo.label}</span>
                      </td>
                      <td style={{ fontWeight: 700, color: 'var(--color-success-700, #15803d)' }}>
                        ৳{Number(trx.paidamount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                      </td>
                      <td>
                        <span className={`innova-badge ${statusInfo.badgeClass}`}>{statusInfo.label}</span>
                      </td>
                      <td style={{ maxWidth: '200px', textOverflow: 'ellipsis', overflow: 'hidden', whiteSpace: 'nowrap' }}>
                        {trx.trxID ? (
                          <span style={{ fontFamily: 'monospace', fontWeight: 600 }}>{trx.trxID}</span>
                        ) : (
                          trx.paidremark || '—'
                        )}
                      </td>
                      <td style={{ textAlign: 'right' }}>
                        {onViewReceipt && (
                          <button
                            type="button"
                            className="innova-btn innova-btn-sm innova-btn-secondary"
                            onClick={() => onViewReceipt(trx.id)}
                            title="Printable Payslip Voucher"
                          >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <polyline points="6 9 6 2 18 2 18 9" />
                              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                              <rect x="6" y="14" width="12" height="8" />
                            </svg>
                            Receipt
                          </button>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};

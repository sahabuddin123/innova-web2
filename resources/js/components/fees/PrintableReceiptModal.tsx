import React, { useState, useEffect } from 'react';
import { FeeReceiptData } from '../../types';
import { feeService } from '../../services';
import { LoadingSpinner, Alert } from '../common';

interface PrintableReceiptModalProps {
  isOpen: boolean;
  onClose: () => void;
  transactionId: number | null;
  preloadedReceipt?: FeeReceiptData | null;
}

export const PrintableReceiptModal: React.FC<PrintableReceiptModalProps> = ({
  isOpen,
  onClose,
  transactionId,
  preloadedReceipt,
}) => {
  const [receipt, setReceipt] = useState<FeeReceiptData | null>(preloadedReceipt || null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isOpen) {
      if (preloadedReceipt) {
        setReceipt(preloadedReceipt);
        setError(null);
      } else if (transactionId) {
        setIsLoading(true);
        setError(null);
        feeService
          .getReceipt(transactionId)
          .then((data) => setReceipt(data))
          .catch((err: unknown) => {
            setError(err instanceof Error ? err.message : 'Failed to retrieve receipt details.');
          })
          .finally(() => setIsLoading(false));
      }
    } else {
      setReceipt(null);
      setError(null);
    }
  }, [isOpen, transactionId, preloadedReceipt]);

  const handlePrint = () => {
    window.print();
  };

  if (!isOpen) return null;

  return (
    <div className="innova-modal-backdrop" onClick={onClose}>
      <div
        className="innova-modal-content innova-modal-lg"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '820px', background: '#fff' }}
      >
        <div className="innova-modal-header no-print">
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
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
                <polyline points="10 9 9 9 8 9" />
              </svg>
            </div>
            <div>
              <h2 className="innova-modal-title" style={{ margin: 0 }}>
                Student Payment Receipt & Voucher
              </h2>
              <p style={{ margin: '2px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                Official Institute Money Receipt & Accounting Voucher
              </p>
            </div>
          </div>
          <button type="button" className="innova-modal-close" onClick={onClose} aria-label="Close">
            &times;
          </button>
        </div>

        <div className="innova-modal-body" style={{ maxHeight: '78vh', overflowY: 'auto' }}>
          {error && (
            <Alert type="error">
              {error}
            </Alert>
          )}

          {isLoading ? (
            <div style={{ padding: '48px', textAlign: 'center' }}>
              <LoadingSpinner />
              <div style={{ marginTop: '12px', fontSize: '14px', color: 'var(--color-text-muted)' }}>
                Generating official receipt voucher...
              </div>
            </div>
          ) : receipt ? (
            <div className="printable-receipt-container" style={{ color: '#1e293b', fontFamily: 'sans-serif' }}>
              {/* Receipt Header */}
              <div
                style={{
                  borderBottom: '2px solid #2563eb',
                  paddingBottom: '16px',
                  marginBottom: '20px',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'flex-start',
                }}
              >
                <div>
                  <h1
                    style={{
                      margin: 0,
                      fontSize: '22px',
                      fontWeight: 800,
                      letterSpacing: '-0.5px',
                      color: '#1e3a8a',
                    }}
                  >
                    INNOVA COMPUTERS BD
                  </h1>
                  <div style={{ fontSize: '12px', color: '#64748b', marginTop: '4px', lineHeight: 1.4 }}>
                    Govt. Reg. Technical Training & Skill Development Institute
                    <br />
                    Rangpur Branch, Contact: +880 1700-000000 | www.innovabd.net
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div
                    style={{
                      display: 'inline-block',
                      padding: '4px 12px',
                      borderRadius: '4px',
                      background: '#eff6ff',
                      color: '#1d4ed8',
                      fontWeight: 700,
                      fontSize: '12px',
                      letterSpacing: '0.5px',
                      border: '1px solid #bfdbfe',
                      marginBottom: '6px',
                    }}
                  >
                    OFFICE & STUDENT COPY
                  </div>
                  <div style={{ fontSize: '15px', fontWeight: 800, color: '#0f172a' }}>
                    {receipt.receipt_number}
                  </div>
                  <div style={{ fontSize: '12px', color: '#64748b' }}>Date: {receipt.paid_date}</div>
                </div>
              </div>

              {/* Student & Payment Summary Grid */}
              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: '1.2fr 1fr',
                  gap: '16px',
                  background: '#f8fafc',
                  border: '1px solid #e2e8f0',
                  borderRadius: '8px',
                  padding: '16px',
                  marginBottom: '20px',
                }}
              >
                <div>
                  <div style={{ fontSize: '11px', textTransform: 'uppercase', color: '#64748b', fontWeight: 700 }}>
                    Candidate Profile
                  </div>
                  <div style={{ fontSize: '16px', fontWeight: 700, color: '#0f172a', marginTop: '2px' }}>
                    {receipt.student.name || 'N/A'}
                  </div>
                  <div style={{ fontSize: '13px', color: '#475569', marginTop: '2px' }}>
                    Phone: {receipt.student.phone || 'N/A'}
                  </div>
                  <div style={{ fontSize: '13px', color: '#475569', marginTop: '2px' }}>
                    Course: <strong>{receipt.student.course_name || 'Enrolled Course'}</strong>
                  </div>
                  <div style={{ fontSize: '13px', color: '#475569', marginTop: '2px' }}>
                    Batch: <strong>{receipt.student.batch_num || 'Assigned Batch'}</strong>
                  </div>
                </div>

                <div style={{ borderLeft: '1px solid #cbd5e1', paddingLeft: '16px' }}>
                  <div style={{ fontSize: '11px', textTransform: 'uppercase', color: '#64748b', fontWeight: 700 }}>
                    Payment Details
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '4px', fontSize: '13px' }}>
                    <span style={{ color: '#64748b' }}>Channel:</span>
                    <span style={{ fontWeight: 600 }}>{receipt.channel}</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '4px', fontSize: '13px' }}>
                    <span style={{ color: '#64748b' }}>Purpose:</span>
                    <span style={{ fontWeight: 600 }}>{receipt.payment_type}</span>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '4px', fontSize: '13px' }}>
                    <span style={{ color: '#64748b' }}>Status:</span>
                    <span
                      style={{
                        fontWeight: 700,
                        color: receipt.status === 'Approved' ? '#16a34a' : '#d97706',
                      }}
                    >
                      {receipt.status}
                    </span>
                  </div>
                  {receipt.trx_id && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '4px', fontSize: '12px' }}>
                      <span style={{ color: '#64748b' }}>Gateway Ref:</span>
                      <span style={{ fontFamily: 'monospace', fontWeight: 600 }}>{receipt.trx_id}</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Transaction Voucher Line */}
              <table
                style={{
                  width: '100%',
                  borderCollapse: 'collapse',
                  marginBottom: '20px',
                  fontSize: '14px',
                }}
              >
                <thead>
                  <tr style={{ background: '#f1f5f9', borderBottom: '2px solid #cbd5e1' }}>
                    <th style={{ textAlign: 'left', padding: '10px 12px', color: '#334155' }}>Payment Item</th>
                    <th style={{ textAlign: 'left', padding: '10px 12px', color: '#334155' }}>Notes / Remarks</th>
                    <th style={{ textAlign: 'right', padding: '10px 12px', color: '#334155' }}>Amount Paid (BDT)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr style={{ borderBottom: '1px solid #e2e8f0' }}>
                    <td style={{ padding: '12px', fontWeight: 600 }}>{receipt.payment_type}</td>
                    <td style={{ padding: '12px', color: '#475569' }}>{receipt.paid_remark || 'Tuition fee installment'}</td>
                    <td style={{ padding: '12px', textAlign: 'right', fontWeight: 700, fontSize: '16px', color: '#16a34a' }}>
                      ৳{Number(receipt.paid_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                    </td>
                  </tr>
                </tbody>
              </table>

              {/* In Words Callout */}
              <div
                style={{
                  padding: '10px 14px',
                  background: '#f8fafc',
                  borderLeft: '4px solid #2563eb',
                  borderRadius: '4px',
                  fontSize: '13px',
                  marginBottom: '24px',
                }}
              >
                <strong>In Words:</strong> {receipt.amount_in_words}
              </div>

              {/* Complete Financial Account Summary Ledger */}
              <div
                style={{
                  width: '340px',
                  marginLeft: 'auto',
                  border: '1px solid #e2e8f0',
                  borderRadius: '8px',
                  padding: '14px',
                  background: '#fff',
                  marginBottom: '40px',
                }}
              >
                <div style={{ fontSize: '12px', fontWeight: 700, color: '#475569', marginBottom: '8px', textTransform: 'uppercase' }}>
                  Student Account Balance
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', padding: '4px 0', fontSize: '13px' }}>
                  <span style={{ color: '#64748b' }}>Total Course Fee:</span>
                  <span>৳{Number(receipt.account_summary.total_course_fee).toLocaleString()}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', padding: '4px 0', fontSize: '13px' }}>
                  <span style={{ color: '#64748b' }}>Special Discount:</span>
                  <span style={{ color: '#2563eb' }}>-৳{Number(receipt.account_summary.discount).toLocaleString()}</span>
                </div>
                <div
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    padding: '6px 0',
                    fontSize: '13px',
                    fontWeight: 600,
                    borderTop: '1px dashed #cbd5e1',
                  }}
                >
                  <span>Net Course Payable:</span>
                  <span>৳{Number(receipt.account_summary.net_payable).toLocaleString()}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', padding: '4px 0', fontSize: '13px' }}>
                  <span style={{ color: '#64748b' }}>Total Paid to Date:</span>
                  <span style={{ color: '#16a34a', fontWeight: 600 }}>
                    ৳{Number(receipt.account_summary.total_paid).toLocaleString()}
                  </span>
                </div>
                <div
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    padding: '8px 0 4px',
                    fontSize: '15px',
                    fontWeight: 800,
                    borderTop: '2px solid #0f172a',
                    marginTop: '4px',
                  }}
                >
                  <span style={{ color: receipt.account_summary.net_due > 0 ? '#dc2626' : '#16a34a' }}>
                    Current Net Due:
                  </span>
                  <span style={{ color: receipt.account_summary.net_due > 0 ? '#dc2626' : '#16a34a' }}>
                    ৳{Number(receipt.account_summary.net_due).toLocaleString()}
                  </span>
                </div>
              </div>

              {/* Signatures Footer */}
              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  paddingTop: '24px',
                  borderTop: '1px solid #e2e8f0',
                  marginTop: '20px',
                }}
              >
                <div style={{ textAlign: 'center', width: '200px' }}>
                  <div style={{ borderBottom: '1px solid #94a3b8', height: '24px', marginBottom: '8px' }}></div>
                  <div style={{ fontSize: '12px', fontWeight: 600, color: '#475569' }}>Student's Signature</div>
                </div>

                <div style={{ textAlign: 'center', width: '200px' }}>
                  <div style={{ borderBottom: '1px solid #94a3b8', height: '24px', marginBottom: '8px' }}></div>
                  <div style={{ fontSize: '12px', fontWeight: 600, color: '#475569' }}>
                    Authorized Cashier / Accounts
                  </div>
                </div>
              </div>
            </div>
          ) : null}
        </div>

        <div className="innova-modal-footer no-print">
          <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
            Close
          </button>
          {transactionId && (
            <a
              href={`/admin/fees/${transactionId}/payslip/download`}
              target="_blank"
              rel="noopener noreferrer"
              className="innova-btn innova-btn-secondary"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Download PDF
            </a>
          )}
          <button type="button" className="innova-btn innova-btn-primary" onClick={handlePrint} disabled={!receipt}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="6 9 6 2 18 2 18 9" />
              <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
              <rect x="6" y="14" width="12" height="8" />
            </svg>
            Print Voucher
          </button>
        </div>
      </div>
    </div>
  );
};

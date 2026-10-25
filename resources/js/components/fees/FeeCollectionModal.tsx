import React, { useState, useEffect } from 'react';
import {
  FeeTransaction,
  FeeFinancialSummary,
  StoreFeeTransactionPayload,
  StudentFeeBalanceResponse,
  Student,
} from '../../types';
import { feeService, studentService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface FeeCollectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (trx: FeeTransaction, summary: FeeFinancialSummary) => void;
  initialStudentId?: number | null;
}

export const FeeCollectionModal: React.FC<FeeCollectionModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  initialStudentId,
}) => {
  // Student Search / Selection
  const [studentSearch, setStudentSearch] = useState<string>('');
  const [searchResults, setSearchResults] = useState<Student[]>([]);
  const [isSearching, setIsSearching] = useState<boolean>(false);
  const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);

  // Student Balance Assessment
  const [balanceData, setBalanceData] = useState<StudentFeeBalanceResponse | null>(null);
  const [isLoadingBalance, setIsLoadingBalance] = useState<boolean>(false);

  // Form Fields
  const [paidAmount, setPaidAmount] = useState<string>('');
  const [paymentType, setPaymentType] = useState<number>(1); // 1 = Course Fee, 2 = Registration Fee
  const [channel, setChannel] = useState<number>(1); // 1 = Cash Counter, 2 = bKash Gateway
  const [paidDate, setPaidDate] = useState<string>(() => new Date().toISOString().split('T')[0]);
  const [paidRemark, setPaidRemark] = useState<string>('');

  // States
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  // Reset or load initial student
  useEffect(() => {
    if (isOpen) {
      setFormError(null);
      setFieldErrors({});
      setPaidAmount('');
      setPaymentType(1);
      setChannel(1);
      setPaidDate(new Date().toISOString().split('T')[0]);
      setPaidRemark('');

      if (initialStudentId) {
        loadStudentDetails(initialStudentId);
      } else {
        setSelectedStudent(null);
        setBalanceData(null);
        setStudentSearch('');
        setSearchResults([]);
      }
    }
  }, [isOpen, initialStudentId]);

  const loadStudentDetails = async (studentId: number) => {
    setIsLoadingBalance(true);
    setFormError(null);
    try {
      const [studentData, balance] = await Promise.all([
        studentService.getStudent(studentId),
        feeService.getStudentBalance(studentId),
      ]);
      setSelectedStudent(studentData);
      setBalanceData(balance);
      setPaidRemark(`Payment for ${studentData.sname}`);
    } catch (err: unknown) {
      setFormError(err instanceof Error ? err.message : 'Failed to load student balance details.');
    } finally {
      setIsLoadingBalance(false);
    }
  };

  // Search students debounce
  useEffect(() => {
    if (!studentSearch || studentSearch.trim().length < 2 || selectedStudent) {
      setSearchResults([]);
      return;
    }

    const timer = setTimeout(async () => {
      setIsSearching(true);
      try {
        const res = await studentService.getStudents({ search: studentSearch.trim(), per_page: 10 });
        setSearchResults(res.data);
      } catch {
        // silent search failure
      } finally {
        setIsSearching(false);
      }
    }, 300);

    return () => clearTimeout(timer);
  }, [studentSearch, selectedStudent]);

  const handleSelectStudent = (student: Student) => {
    setSelectedStudent(student);
    setSearchResults([]);
    setStudentSearch('');
    loadStudentDetails(student.id);
  };

  const handleClearStudent = () => {
    setSelectedStudent(null);
    setBalanceData(null);
    setPaidAmount('');
    setPaidRemark('');
  };

  // Calculate live projected balance
  const dueAmount = balanceData?.account_summary?.due_course_fee ?? 0;
  const numPaid = parseFloat(paidAmount) || 0;
  const projectedRemaining = Math.max(0, dueAmount - numPaid);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!selectedStudent) {
      setFormError('Please select a student record to collect fees.');
      return;
    }

    if (!paidAmount || isNaN(numPaid) || numPaid <= 0) {
      setFormError('Please enter a valid positive payment amount.');
      return;
    }

    if (!paidRemark || paidRemark.trim().length === 0) {
      setFormError('Please provide remarks or payment notes for this transaction.');
      return;
    }

    setIsSubmitting(true);
    setFormError(null);
    setFieldErrors({});

    const payload: StoreFeeTransactionPayload = {
      student_id: selectedStudent.id,
      paidamount: numPaid.toFixed(2),
      paymentType,
      paiddate: paidDate,
      paidremark: paidRemark.trim(),
      channel,
    };

    try {
      const res = await feeService.createTransaction(payload);
      onSuccess(res.transaction, res.financial_summary);
      onClose();
    } catch (err: unknown) {
      const errorObj = err as { message?: string; errors?: Record<string, string[]> };
      setFormError(errorObj.message || 'Failed to record fee payment.');
      if (errorObj.errors) {
        setFieldErrors(errorObj.errors);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className="innova-modal-backdrop" onClick={onClose}>
      <div
        className="innova-modal-content innova-modal-lg"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '780px' }}
      >
        <div className="innova-modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(34, 197, 94, 0.1)',
                color: 'var(--color-success-600, #16a34a)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <line x1="12" y1="1" x2="12" y2="23" />
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
              </svg>
            </div>
            <div>
              <h2 className="innova-modal-title" style={{ margin: 0 }}>
                Collect Fee Payment
              </h2>
              <p style={{ margin: '2px 0 0', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                Record manual counter payment or verified online fee deposit
              </p>
            </div>
          </div>
          <button type="button" className="innova-modal-close" onClick={onClose} aria-label="Close">
            &times;
          </button>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="innova-modal-body" style={{ maxHeight: '72vh', overflowY: 'auto' }}>
            {formError && (
              <Alert type="error">
                {formError}
              </Alert>
            )}

            {/* Step 1: Student Selection */}
            <div style={{ marginBottom: '18px' }}>
              <label className="innova-label" style={{ fontWeight: 600 }}>
                Target Student <span style={{ color: 'var(--color-error-600)' }}>*</span>
              </label>

              {!selectedStudent ? (
                <div style={{ position: 'relative' }}>
                  <input
                    type="text"
                    className="innova-input"
                    placeholder="Search by student name, phone number, or SID..."
                    value={studentSearch}
                    onChange={(e) => setStudentSearch(e.target.value)}
                    autoFocus
                  />
                  {isSearching && (
                    <div style={{ position: 'absolute', right: '12px', top: '10px' }}>
                      <LoadingSpinner size="sm" />
                    </div>
                  )}

                  {searchResults.length > 0 && (
                    <div
                      style={{
                        position: 'absolute',
                        top: '100%',
                        left: 0,
                        right: 0,
                        background: 'var(--color-bg-card, #fff)',
                        border: '1px solid var(--color-border)',
                        borderRadius: '8px',
                        boxShadow: 'var(--shadow-lg)',
                        zIndex: 20,
                        maxHeight: '220px',
                        overflowY: 'auto',
                        marginTop: '4px',
                      }}
                    >
                      {searchResults.map((stu) => (
                        <div
                          key={stu.id}
                          onClick={() => handleSelectStudent(stu)}
                          style={{
                            padding: '10px 14px',
                            cursor: 'pointer',
                            borderBottom: '1px solid var(--color-border-light, #f1f5f9)',
                            display: 'flex',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                          }}
                          onMouseEnter={(e) => (e.currentTarget.style.backgroundColor = 'var(--color-bg-hover, #f8fafc)')}
                          onMouseLeave={(e) => (e.currentTarget.style.backgroundColor = 'transparent')}
                        >
                          <div>
                            <div style={{ fontWeight: 600, color: 'var(--color-text-main)' }}>{stu.sname}</div>
                            <div style={{ fontSize: '12px', color: 'var(--color-text-muted)' }}>
                              Phone: {stu.phone} | Course: {stu.course?.name || `Course #${stu.course_id}`}
                            </div>
                          </div>
                          <button
                            type="button"
                            className="innova-btn innova-btn-sm innova-btn-secondary"
                            style={{ pointerEvents: 'none' }}
                          >
                            Select
                          </button>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '12px 16px',
                    borderRadius: '8px',
                    background: 'var(--color-bg-subtle, #f8fafc)',
                    border: '1px solid var(--color-border)',
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                    <div
                      style={{
                        width: '40px',
                        height: '40px',
                        borderRadius: '50%',
                        background: 'var(--color-primary-100, #e0e7ff)',
                        color: 'var(--color-primary-700, #4338ca)',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontWeight: 700,
                        fontSize: '16px',
                      }}
                    >
                      {selectedStudent.sname.charAt(0).toUpperCase()}
                    </div>
                    <div>
                      <div style={{ fontWeight: 600, color: 'var(--color-text-main)' }}>{selectedStudent.sname}</div>
                      <div style={{ fontSize: '13px', color: 'var(--color-text-muted)' }}>
                        Phone: {selectedStudent.phone} | Enrolled:{' '}
                        {selectedStudent.course?.name || `Course #${selectedStudent.course_id}`} (
                        {selectedStudent.batch?.batchnum || `Batch #${selectedStudent.batch_id}`})
                      </div>
                    </div>
                  </div>
                  {!initialStudentId && (
                    <button
                      type="button"
                      className="innova-btn innova-btn-sm innova-btn-secondary"
                      onClick={handleClearStudent}
                    >
                      Change Student
                    </button>
                  )}
                </div>
              )}
            </div>

            {/* Step 2: Student Financial Diagnostic Card */}
            {isLoadingBalance ? (
              <div style={{ padding: '24px', textAlign: 'center' }}>
                <LoadingSpinner />
                <div style={{ fontSize: '13px', color: 'var(--color-text-muted)', marginTop: '8px' }}>
                  Loading student ledger & fee balances...
                </div>
              </div>
            ) : balanceData ? (
              <div
                style={{
                  background: 'var(--color-bg-subtle, #f8fafc)',
                  border: '1px solid var(--color-border)',
                  borderRadius: '10px',
                  padding: '16px',
                  marginBottom: '20px',
                }}
              >
                <div style={{ fontSize: '12px', fontWeight: 600, textTransform: 'uppercase', color: 'var(--color-text-muted)', marginBottom: '10px' }}>
                  Current Fee Account Snapshot
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '12px' }}>
                  <div style={{ padding: '10px', background: '#fff', borderRadius: '6px', border: '1px solid var(--color-border-light, #e2e8f0)' }}>
                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Total Course Fee</div>
                    <div style={{ fontSize: '15px', fontWeight: 700, color: 'var(--color-text-main)' }}>
                      ৳{Number(balanceData.account_summary.total_course_fee).toLocaleString()}
                    </div>
                  </div>
                  <div style={{ padding: '10px', background: '#fff', borderRadius: '6px', border: '1px solid var(--color-border-light, #e2e8f0)' }}>
                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Special Discount</div>
                    <div style={{ fontSize: '15px', fontWeight: 700, color: 'var(--color-primary-600, #4f46e5)' }}>
                      ৳{Number(balanceData.account_summary.discount).toLocaleString()}
                    </div>
                  </div>
                  <div style={{ padding: '10px', background: '#fff', borderRadius: '6px', border: '1px solid var(--color-border-light, #e2e8f0)' }}>
                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Net Payable</div>
                    <div style={{ fontSize: '15px', fontWeight: 700, color: 'var(--color-text-main)' }}>
                      ৳{Number(balanceData.account_summary.net_payable).toLocaleString()}
                    </div>
                  </div>
                  <div style={{ padding: '10px', background: '#fff', borderRadius: '6px', border: '1px solid var(--color-border-light, #e2e8f0)' }}>
                    <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Total Already Paid</div>
                    <div style={{ fontSize: '15px', fontWeight: 700, color: 'var(--color-success-600, #16a34a)' }}>
                      ৳{Number(balanceData.account_summary.total_paid_course).toLocaleString()}
                    </div>
                  </div>
                  <div
                    style={{
                      padding: '10px',
                      background: dueAmount > 0 ? 'rgba(239, 68, 68, 0.08)' : 'rgba(34, 197, 94, 0.08)',
                      borderRadius: '6px',
                      border: `1px solid ${dueAmount > 0 ? 'rgba(239, 68, 68, 0.2)' : 'rgba(34, 197, 94, 0.2)'}`,
                    }}
                  >
                    <div style={{ fontSize: '11px', color: dueAmount > 0 ? 'var(--color-error-600, #dc2626)' : 'var(--color-success-600, #16a34a)', fontWeight: 600 }}>
                      Current Net Due
                    </div>
                    <div style={{ fontSize: '16px', fontWeight: 800, color: dueAmount > 0 ? 'var(--color-error-700, #b91c1c)' : 'var(--color-success-700, #15803d)' }}>
                      ৳{Number(dueAmount).toLocaleString()}
                    </div>
                  </div>
                </div>
              </div>
            ) : null}

            {/* Step 3: Payment Entry Fields */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' }}>
              <div>
                <label className="innova-label" htmlFor="fee-amount">
                  Payment Amount (BDT ৳) <span style={{ color: 'var(--color-error-600)' }}>*</span>
                </label>
                <div style={{ position: 'relative' }}>
                  <span
                    style={{
                      position: 'absolute',
                      left: '12px',
                      top: '10px',
                      color: 'var(--color-text-muted)',
                      fontWeight: 600,
                    }}
                  >
                    ৳
                  </span>
                  <input
                    id="fee-amount"
                    type="number"
                    step="0.01"
                    min="1"
                    className={`innova-input ${fieldErrors.paidamount ? 'innova-input-error' : ''}`}
                    style={{ paddingLeft: '28px', fontSize: '16px', fontWeight: 600 }}
                    placeholder="0.00"
                    value={paidAmount}
                    onChange={(e) => setPaidAmount(e.target.value)}
                    required
                  />
                </div>
                {fieldErrors.paidamount && (
                  <span style={{ fontSize: '12px', color: 'var(--color-error-600)' }}>
                    {fieldErrors.paidamount[0]}
                  </span>
                )}
                {numPaid > dueAmount && paymentType === 1 && dueAmount > 0 && (
                  <div style={{ fontSize: '12px', color: 'var(--color-warning-600, #d97706)', marginTop: '4px' }}>
                    Note: Paid amount exceeds currently calculated course fee dues (৳{dueAmount.toLocaleString()}).
                  </div>
                )}
              </div>

              <div>
                <label className="innova-label" htmlFor="fee-payment-type">
                  Payment Type <span style={{ color: 'var(--color-error-600)' }}>*</span>
                </label>
                <select
                  id="fee-payment-type"
                  className="innova-input"
                  value={paymentType}
                  onChange={(e) => setPaymentType(Number(e.target.value))}
                >
                  <option value={1}>Course Fee Payment (Deducts from Dues)</option>
                  <option value={2}>Registration Fee Payment</option>
                </select>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '16px', marginBottom: '16px' }}>
              <div>
                <label className="innova-label" htmlFor="fee-channel">
                  Collection Channel <span style={{ color: 'var(--color-error-600)' }}>*</span>
                </label>
                <select
                  id="fee-channel"
                  className="innova-input"
                  value={channel}
                  onChange={(e) => setChannel(Number(e.target.value))}
                >
                  <option value={1}>Cash Counter (Immediate Approval)</option>
                  <option value={2}>bKash Gateway / Manual Entry</option>
                </select>
              </div>

              <div>
                <label className="innova-label" htmlFor="fee-date">
                  Payment Date <span style={{ color: 'var(--color-error-600)' }}>*</span>
                </label>
                <input
                  id="fee-date"
                  type="date"
                  className={`innova-input ${fieldErrors.paiddate ? 'innova-input-error' : ''}`}
                  value={paidDate}
                  onChange={(e) => setPaidDate(e.target.value)}
                  required
                />
              </div>
            </div>

            <div style={{ marginBottom: '16px' }}>
              <label className="innova-label" htmlFor="fee-remark">
                Payment Remarks / Reference <span style={{ color: 'var(--color-error-600)' }}>*</span>
              </label>
              <input
                id="fee-remark"
                type="text"
                className={`innova-input ${fieldErrors.paidremark ? 'innova-input-error' : ''}`}
                placeholder="e.g. 1st installment cash receipt or bKash TrxID"
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

            {/* Live Financial Projection Box */}
            {selectedStudent && numPaid > 0 && (
              <div
                style={{
                  background: 'var(--color-bg-hover, #f1f5f9)',
                  borderRadius: '8px',
                  padding: '12px 16px',
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                }}
              >
                <div>
                  <div style={{ fontSize: '13px', fontWeight: 600, color: 'var(--color-text-main)' }}>
                    Immediate Balance Update
                  </div>
                  <div style={{ fontSize: '12px', color: 'var(--color-text-muted)' }}>
                    Collecting ৳{numPaid.toLocaleString()} for {selectedStudent.sname}
                  </div>
                </div>
                <div style={{ textAlign: 'right' }}>
                  <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>Remaining Due After Payment</div>
                  <div style={{ fontSize: '16px', fontWeight: 800, color: projectedRemaining === 0 ? 'var(--color-success-600)' : 'var(--color-text-main)' }}>
                    ৳{projectedRemaining.toLocaleString()}
                  </div>
                </div>
              </div>
            )}
          </div>

          <div className="innova-modal-footer">
            <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose} disabled={isSubmitting}>
              Cancel
            </button>
            <button
              type="submit"
              className="innova-btn innova-btn-primary"
              disabled={isSubmitting || !selectedStudent || numPaid <= 0}
              style={{ background: 'var(--color-success-600, #16a34a)', borderColor: 'var(--color-success-600, #16a34a)' }}
            >
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" /> Recording Payment...
                </>
              ) : (
                <>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                  Confirm & Post Fee Payment
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

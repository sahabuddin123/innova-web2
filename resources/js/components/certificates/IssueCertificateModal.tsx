import React, { useState, useEffect } from 'react';
import {
  CertificateIcbd,
  CertificateEligibility,
  Course,
  Batch,
  Student,
  IcbdFormData,
} from '../../types';
import { certificateService, courseService, batchService, studentService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface IssueCertificateModalProps {
  isOpen: boolean;
  preselectedStudentId?: number | null;
  onClose: () => void;
  onSuccess: (certificate: CertificateIcbd) => void;
}

export const IssueCertificateModal: React.FC<IssueCertificateModalProps> = ({
  isOpen,
  preselectedStudentId = null,
  onClose,
  onSuccess,
}) => {
  if (!isOpen) return null;

  // Form fields
  const [studentId, setStudentId] = useState<number | ''>(preselectedStudentId || '');
  const [courseId, setCourseId] = useState<number | ''>('');
  const [batchId, setBatchId] = useState<number | ''>('');
  const [certificateNo, setCertificateNo] = useState<string>('');
  const [theoryHours, setTheoryHours] = useState<number>(0);
  const [practicalHours, setPracticalHours] = useState<number>(0);
  const [internHours, setInternHours] = useState<number>(0);
  const [result, setResult] = useState<string>('A+');
  const [issueDate, setIssueDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [receiver, setReceiver] = useState<string>('');
  const [deliveryDate, setDeliveryDate] = useState<string>('');
  const [status, setStatus] = useState<number>(2); // Default to Processing (2)

  // Catalogs
  const [students, setStudents] = useState<Student[]>([]);
  const [courses, setCourses] = useState<Course[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [loadingCatalogs, setLoadingCatalogs] = useState<boolean>(true);

  // Student Eligibility assessment
  const [checkingEligibility, setCheckingEligibility] = useState<boolean>(false);
  const [eligibility, setEligibility] = useState<CertificateEligibility | null>(null);

  // Submission & error state
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  // Load catalogs on mount
  useEffect(() => {
    let isMounted = true;
    setLoadingCatalogs(true);

    Promise.all([
      studentService.getStudents({ per_page: 100 }),
      courseService.getCourses({ per_page: 100 }),
      batchService.getBatches({ per_page: 100 }),
    ])
      .then(([studentsRes, coursesRes, batchesRes]) => {
        if (!isMounted) return;
        setStudents(studentsRes.data || []);
        setCourses(coursesRes.data || []);
        setBatches(batchesRes.data || []);
      })
      .catch((err) => {
        console.error('Failed to load certificate issuance catalogs:', err);
      })
      .finally(() => {
        if (isMounted) setLoadingCatalogs(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  // When studentId changes, trigger eligibility check
  useEffect(() => {
    if (!studentId) {
      setEligibility(null);
      return;
    }

    let isMounted = true;
    setCheckingEligibility(true);
    setServerError(null);

    certificateService
      .checkEligibility(studentId)
      .then((data) => {
        if (!isMounted) return;
        setEligibility(data);

        // Auto-fill course and batch from student's record if available
        if (data.student?.course?.id) {
          setCourseId(data.student.course.id);
        }
        if (data.student?.batch?.id) {
          setBatchId(data.student.batch.id);
        }
      })
      .catch((err) => {
        if (!isMounted) return;
        console.error('Eligibility check error:', err);
        setEligibility(null);
      })
      .finally(() => {
        if (isMounted) setCheckingEligibility(false);
      });

    return () => {
      isMounted = false;
    };
  }, [studentId]);

  const totalHours = Number(theoryHours || 0) + Number(practicalHours || 0) + Number(internHours || 0);

  const canSubmit =
    Boolean(studentId) &&
    Boolean(courseId) &&
    Boolean(batchId) &&
    (!eligibility || eligibility.can_issue_icbd) &&
    !isSubmitting;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!canSubmit) return;

    setIsSubmitting(true);
    setServerError(null);
    setValidationErrors({});

    const payload: IcbdFormData = {
      student_id: Number(studentId),
      course_id: Number(courseId),
      batch_id: Number(batchId),
      theory_hours: Number(theoryHours || 0),
      practical_hours: Number(practicalHours || 0),
      intern_hours: Number(internHours || 0),
      result: result.trim() || undefined,
      issue_date: issueDate || undefined,
      certificate_receiver: receiver.trim() || undefined,
      delivery_date: deliveryDate || undefined,
      is_active: status,
    };

    if (certificateNo.trim()) {
      payload.certificate_no = certificateNo.trim();
    }

    try {
      const created = await certificateService.issueIcbdCertificate(payload);
      onSuccess(created);
      onClose();
    } catch (err: unknown) {
      const apiErr = err as {
        response?: {
          status?: number;
          data?: {
            message?: string;
            errors?: Record<string, string[]>;
            error?: string;
          };
        };
        message?: string;
      };

      if (apiErr?.response?.status === 409) {
        setServerError(
          apiErr.response.data?.message ||
            'Duplicate Issuance: A certificate has already been issued for this student and course.'
        );
      } else if (apiErr?.response?.data?.errors) {
        setValidationErrors(apiErr.response.data.errors);
        setServerError('Please review the errors highlighted below.');
      } else {
        setServerError(
          apiErr?.response?.data?.message ||
            apiErr?.message ||
            'Failed to issue certificate. Please verify student eligibility.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="innova-modal"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '720px', width: '95%' }}
      >
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Academic Certification
            </div>
            <h2 className="innova-modal-title">Issue Institutional (ICBD) Certificate</h2>
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

        {/* Body */}
        <form onSubmit={handleSubmit}>
          <div className="innova-modal-body" style={{ maxHeight: '72vh', overflowY: 'auto' }}>
            {serverError && (
              <div style={{ marginBottom: '1rem' }}>
                <Alert type="error" title="Certificate Issuance Error">
                  {serverError}
                </Alert>
              </div>
            )}

            {/* Student Selector */}
            <div className="innova-form-group" style={{ marginBottom: '1rem' }}>
              <label className="innova-form-label" htmlFor="issue_student_id">
                Select Candidate Student <span style={{ color: 'var(--innova-error)' }}>*</span>
              </label>
              {loadingCatalogs ? (
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', padding: '0.5rem 0' }}>
                  <LoadingSpinner size="sm" />
                  <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>Loading candidate directory...</span>
                </div>
              ) : (
                <select
                  id="issue_student_id"
                  className="innova-form-control"
                  value={studentId}
                  onChange={(e) => setStudentId(e.target.value ? Number(e.target.value) : '')}
                  required
                >
                  <option value="">-- Choose Candidate --</option>
                  {students.map((st) => (
                    <option key={st.id} value={st.id}>
                      #{st.id} — {st.sname} ({st.phone || 'No Phone'})
                    </option>
                  ))}
                </select>
              )}
              {validationErrors.student_id && (
                <span className="innova-form-error">{validationErrors.student_id[0]}</span>
              )}
            </div>

            {/* Eligibility Assessment Status Card */}
            {checkingEligibility && (
              <div style={{ marginBottom: '1rem', padding: '0.75rem', background: 'var(--innova-gray-50)', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <LoadingSpinner size="sm" />
                <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
                  Assessing candidate eligibility, active enrollment, and tuition clearance...
                </span>
              </div>
            )}

            {eligibility && !checkingEligibility && (
              <div style={{ marginBottom: '1.25rem' }}>
                {!eligibility.can_issue_icbd ? (
                  <Alert type="error" title="Candidate Ineligible for ICBD Certificate">
                    <div>
                      {eligibility.reasons.map((r, i) => (
                        <div key={i}>• {r}</div>
                      ))}
                    </div>
                  </Alert>
                ) : (
                  <div>
                    {eligibility.has_outstanding_dues ? (
                      <Alert type="warning" title="Outstanding Tuition Dues Warning">
                        <div>
                          Candidate has an outstanding balance of{' '}
                          <strong>৳{eligibility.student.balance}</strong>. Proceed only if fee clearance has been approved.
                        </div>
                      </Alert>
                    ) : (
                      <div
                        style={{
                          background: 'rgba(16, 185, 129, 0.08)',
                          border: '1px solid rgba(16, 185, 129, 0.3)',
                          borderRadius: '6px',
                          padding: '0.65rem 0.85rem',
                          display: 'flex',
                          alignItems: 'center',
                          gap: '0.5rem',
                          color: '#065f46',
                          fontSize: '0.85rem',
                        }}
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" strokeWidth="2.5">
                          <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <span>
                          <strong>Candidate Eligible:</strong> Active student account with zero balance.
                        </span>
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}

            {/* Program & Batch Row */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="issue_course_id">
                  Course Program <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <select
                  id="issue_course_id"
                  className="innova-form-control"
                  value={courseId}
                  onChange={(e) => setCourseId(e.target.value ? Number(e.target.value) : '')}
                  required
                >
                  <option value="">-- Select Course --</option>
                  {courses.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name} {c.courseduration ? `(${c.courseduration} Mos)` : ''}
                    </option>
                  ))}
                </select>
                {validationErrors.course_id && (
                  <span className="innova-form-error">{validationErrors.course_id[0]}</span>
                )}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="issue_batch_id">
                  Academic Batch <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <select
                  id="issue_batch_id"
                  className="innova-form-control"
                  value={batchId}
                  onChange={(e) => setBatchId(e.target.value ? Number(e.target.value) : '')}
                  required
                >
                  <option value="">-- Select Batch --</option>
                  {batches.map((b) => (
                    <option key={b.id} value={b.id}>
                      {b.batchnum}
                    </option>
                  ))}
                </select>
                {validationErrors.batch_id && (
                  <span className="innova-form-error">{validationErrors.batch_id[0]}</span>
                )}
              </div>
            </div>

            {/* Optional Manual Certificate Serial */}
            <div className="innova-form-group" style={{ marginBottom: '1rem' }}>
              <label className="innova-form-label" htmlFor="issue_cert_no">
                Certificate Serial Number (Optional)
              </label>
              <input
                type="text"
                id="issue_cert_no"
                className="innova-form-control"
                placeholder="Leave blank to auto-generate (e.g. ICBD0001)"
                value={certificateNo}
                onChange={(e) => setCertificateNo(e.target.value)}
              />
              {validationErrors.certificate_no && (
                <span className="innova-form-error">{validationErrors.certificate_no[0]}</span>
              )}
            </div>

            {/* Curriculum Hours Breakdown */}
            <div
              style={{
                background: 'var(--innova-gray-50)',
                padding: '0.85rem',
                borderRadius: '8px',
                border: '1px solid var(--innova-gray-200)',
                marginBottom: '1rem',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                <span style={{ fontSize: '0.75rem', fontWeight: 700, textTransform: 'uppercase', color: 'var(--innova-gray-600)' }}>
                  Curriculum Hours
                </span>
                <span style={{ fontSize: '0.85rem', fontWeight: 700, color: 'var(--innova-primary)' }}>
                  Total Computed: {totalHours} Hours
                </span>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '0.75rem' }}>
                <div className="innova-form-group">
                  <label className="innova-form-label" style={{ fontSize: '0.75rem' }}>Theory (hrs)</label>
                  <input
                    type="number"
                    min="0"
                    className="innova-form-control"
                    value={theoryHours}
                    onChange={(e) => setTheoryHours(Math.max(0, parseInt(e.target.value) || 0))}
                  />
                </div>
                <div className="innova-form-group">
                  <label className="innova-form-label" style={{ fontSize: '0.75rem' }}>Practical (hrs)</label>
                  <input
                    type="number"
                    min="0"
                    className="innova-form-control"
                    value={practicalHours}
                    onChange={(e) => setPracticalHours(Math.max(0, parseInt(e.target.value) || 0))}
                  />
                </div>
                <div className="innova-form-group">
                  <label className="innova-form-label" style={{ fontSize: '0.75rem' }}>Internship (hrs)</label>
                  <input
                    type="number"
                    min="0"
                    className="innova-form-control"
                    value={internHours}
                    onChange={(e) => setInternHours(Math.max(0, parseInt(e.target.value) || 0))}
                  />
                </div>
              </div>
            </div>

            {/* Result & Issue Date */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="issue_result">
                  Academic Result / Grade
                </label>
                <input
                  type="text"
                  id="issue_result"
                  className="innova-form-control"
                  placeholder="e.g. A+, First Class, Excellent"
                  value={result}
                  onChange={(e) => setResult(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="issue_date">
                  Issue Date
                </label>
                <input
                  type="date"
                  id="issue_date"
                  className="innova-form-control"
                  value={issueDate}
                  onChange={(e) => setIssueDate(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="issue_status">
                  Initial Lifecycle State <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <select
                  id="issue_status"
                  className="innova-form-control"
                  value={status}
                  onChange={(e) => setStatus(Number(e.target.value))}
                  required
                >
                  <option value={2}>Processing (Waiting)</option>
                  <option value={3}>Printing Queue</option>
                  <option value={1}>Delivered</option>
                </select>
              </div>
            </div>

            {/* Optional Receiver and Delivery Date */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="issue_receiver">
                  Certificate Receiver Name
                </label>
                <input
                  type="text"
                  id="issue_receiver"
                  className="innova-form-control"
                  placeholder="Recipient full name if delivered"
                  value={receiver}
                  onChange={(e) => setReceiver(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="delivery_date">
                  Delivery Date
                </label>
                <input
                  type="date"
                  id="delivery_date"
                  className="innova-form-control"
                  value={deliveryDate}
                  onChange={(e) => setDeliveryDate(e.target.value)}
                />
              </div>
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
              disabled={!canSubmit}
              style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
            >
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" />
                  <span>Issuing...</span>
                </>
              ) : (
                <>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <circle cx="12" cy="8" r="7" />
                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
                  </svg>
                  <span>Issue Certificate</span>
                </>
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

import React, { useState, useEffect } from 'react';
import { CertificateBteb, CertificateSessionBrief, Student, BtebFormData } from '../../types';
import { certificateService, studentService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface BtebCertificateModalProps {
  certificate?: CertificateBteb | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (certificate: CertificateBteb) => void;
}

export const BtebCertificateModal: React.FC<BtebCertificateModalProps> = ({
  certificate = null,
  isOpen,
  onClose,
  onSuccess,
}) => {
  if (!isOpen) return null;

  const isEdit = Boolean(certificate && certificate.id);

  // Form State
  const [studentId, setStudentId] = useState<number | ''>(certificate?.student_id || '');
  const [sessionId, setSessionId] = useState<number | ''>(certificate?.session_id || '');
  const [regNo, setRegNo] = useState<string>(certificate?.regNo || '');
  const [cno, setCno] = useState<string>(certificate?.cno || '');
  const [pDate, setPDate] = useState<string>(
    certificate?.pDate ? certificate.pDate.split('T')[0] : ''
  );
  const [result, setResult] = useState<string>(certificate?.Result || '');
  const [receiver, setReceiver] = useState<string>(certificate?.recevier || '');
  const [dDate, setDDate] = useState<string>(
    certificate?.dDate ? certificate.dDate.split('T')[0] : ''
  );
  const [isActive, setIsActive] = useState<number>(certificate ? certificate.IsActive : 1);

  // Catalogs
  const [students, setStudents] = useState<Student[]>([]);
  const [sessions, setSessions] = useState<CertificateSessionBrief[]>([]);
  const [loadingCatalogs, setLoadingCatalogs] = useState<boolean>(true);

  // Submission & Validation
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    let isMounted = true;
    setLoadingCatalogs(true);

    Promise.all([studentService.getStudents({ per_page: 100 }), certificateService.getSessions()])
      .then(([studentsRes, sessionsRes]) => {
        if (!isMounted) return;
        setStudents(studentsRes.data || []);
        setSessions(sessionsRes || []);
      })
      .catch((err) => {
        console.error('Failed to load catalogs for BTEB modal:', err);
      })
      .finally(() => {
        if (isMounted) setLoadingCatalogs(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setServerError(null);
    setValidationErrors({});

    const payload: BtebFormData = {
      student_id: Number(studentId),
      session_id: Number(sessionId),
      regNo: regNo.trim(),
      cno: cno.trim() || undefined,
      pDate: pDate || undefined,
      Result: result.trim() || undefined,
      recevier: receiver.trim() || undefined,
      dDate: dDate || undefined,
      IsActive: isActive,
    };

    try {
      let saved: CertificateBteb;
      if (isEdit && certificate) {
        saved = await certificateService.updateBtebCertificate(certificate.id, payload);
      } else {
        saved = await certificateService.createBtebCertificate(payload);
      }
      onSuccess(saved);
      onClose();
    } catch (err: unknown) {
      const apiErr = err as {
        response?: {
          status?: number;
          data?: {
            message?: string;
            errors?: Record<string, string[]>;
          };
        };
        message?: string;
      };

      if (apiErr?.response?.status === 409) {
        setServerError(
          apiErr.response.data?.message ||
            'Duplicate BTEB Certificate: A certificate has already been registered for this student and session.'
        );
      } else if (apiErr?.response?.data?.errors) {
        setValidationErrors(apiErr.response.data.errors);
        setServerError('Please review the highlighted validation errors below.');
      } else {
        setServerError(
          apiErr?.response?.data?.message ||
            apiErr?.message ||
            'Failed to save BTEB certificate record.'
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
        style={{ maxWidth: '680px', width: '95%' }}
      >
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Technical Board (BTEB) Certification
            </div>
            <h2 className="innova-modal-title">
              {isEdit ? `Edit BTEB Certificate #${certificate?.regNo}` : 'Register BTEB Board Certificate'}
            </h2>
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
          <div className="innova-modal-body" style={{ maxHeight: '72vh', overflowY: 'auto' }}>
            {serverError && (
              <div style={{ marginBottom: '1rem' }}>
                <Alert type="error" title="BTEB Record Error">
                  {serverError}
                </Alert>
              </div>
            )}

            {/* Candidate Student */}
            <div className="innova-form-group" style={{ marginBottom: '1rem' }}>
              <label className="innova-form-label" htmlFor="bteb_student_id">
                Candidate Student <span style={{ color: 'var(--innova-error)' }}>*</span>
              </label>
              {loadingCatalogs ? (
                <LoadingSpinner size="sm" />
              ) : isEdit ? (
                <input
                  type="text"
                  className="innova-form-control"
                  value={certificate?.StudentsInfo?.sname || certificate?.student?.sname || `Student #${studentId}`}
                  disabled
                />
              ) : (
                <select
                  id="bteb_student_id"
                  className="innova-form-control"
                  value={studentId}
                  onChange={(e) => setStudentId(e.target.value ? Number(e.target.value) : '')}
                  required
                >
                  <option value="">-- Choose Candidate Student --</option>
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

            {/* BTEB Session & Registration Number */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_session_id">
                  Board Academic Session <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                {loadingCatalogs ? (
                  <LoadingSpinner size="sm" />
                ) : (
                  <select
                    id="bteb_session_id"
                    className="innova-form-control"
                    value={sessionId}
                    onChange={(e) => setSessionId(e.target.value ? Number(e.target.value) : '')}
                    required
                  >
                    <option value="">-- Select Session --</option>
                    {sessions.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.sessionName || s.sessionname || `Session #${s.id}`} {s.year ? `(${s.year})` : ''}
                      </option>
                    ))}
                  </select>
                )}
                {validationErrors.session_id && (
                  <span className="innova-form-error">{validationErrors.session_id[0]}</span>
                )}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_reg_no">
                  Board Registration Number <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <input
                  type="text"
                  id="bteb_reg_no"
                  className="innova-form-control"
                  placeholder="e.g. 19283746"
                  value={regNo}
                  onChange={(e) => setRegNo(e.target.value)}
                  required
                />
                {validationErrors.regNo && (
                  <span className="innova-form-error">{validationErrors.regNo[0]}</span>
                )}
              </div>
            </div>

            {/* Board Certificate Number & Result */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_cno">
                  Board Certificate Serial (cno)
                </label>
                <input
                  type="text"
                  id="bteb_cno"
                  className="innova-form-control"
                  placeholder="e.g. BTEB-2026-908"
                  value={cno}
                  onChange={(e) => setCno(e.target.value)}
                />
                {validationErrors.cno && (
                  <span className="innova-form-error">{validationErrors.cno[0]}</span>
                )}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_result">
                  Academic Result / Grade
                </label>
                <input
                  type="text"
                  id="bteb_result"
                  className="innova-form-control"
                  placeholder="e.g. GPA 5.00 / First Division"
                  value={result}
                  onChange={(e) => setResult(e.target.value)}
                />
              </div>
            </div>

            {/* Passing Date & Lifecycle Status */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_pdate">
                  Passing / Exam Date
                </label>
                <input
                  type="date"
                  id="bteb_pdate"
                  className="innova-form-control"
                  value={pDate}
                  onChange={(e) => setPDate(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_status">
                  Certificate Status <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <select
                  id="bteb_status"
                  className="innova-form-control"
                  value={isActive}
                  onChange={(e) => setIsActive(Number(e.target.value))}
                  required
                >
                  <option value={1}>Active / Delivered</option>
                  <option value={0}>Inactive / Revoked</option>
                </select>
              </div>
            </div>

            {/* Receiver & Delivery Date */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_receiver">
                  Certificate Receiver
                </label>
                <input
                  type="text"
                  id="bteb_receiver"
                  className="innova-form-control"
                  placeholder="Recipient full name"
                  value={receiver}
                  onChange={(e) => setReceiver(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="bteb_ddate">
                  Delivery Date
                </label>
                <input
                  type="date"
                  id="bteb_ddate"
                  className="innova-form-control"
                  value={dDate}
                  onChange={(e) => setDDate(e.target.value)}
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
              disabled={isSubmitting || !studentId || !sessionId || !regNo}
            >
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" />
                  <span>Saving...</span>
                </>
              ) : isEdit ? (
                'Update BTEB Certificate'
              ) : (
                'Register BTEB Certificate'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

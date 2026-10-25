import React, { useState, useEffect } from 'react';
import { CertificateIcbd, Course, Batch, IcbdFormData } from '../../types';
import { certificateService, courseService, batchService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface CertificateFormModalProps {
  certificate: CertificateIcbd | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (updated: CertificateIcbd) => void;
}

export const CertificateFormModal: React.FC<CertificateFormModalProps> = ({
  certificate,
  isOpen,
  onClose,
  onSuccess,
}) => {
  if (!isOpen || !certificate) return null;

  const [courseId, setCourseId] = useState<number>(certificate.course_id);
  const [batchId, setBatchId] = useState<number>(certificate.batch_id);
  const [certificateNo, setCertificateNo] = useState<string>(certificate.certificate_no || '');
  const [theoryHours, setTheoryHours] = useState<number>(certificate.theory_hours || 0);
  const [practicalHours, setPracticalHours] = useState<number>(certificate.practical_hours || 0);
  const [internHours, setInternHours] = useState<number>(certificate.intern_hours || 0);
  const [result, setResult] = useState<string>(certificate.result || '');
  const [issueDate, setIssueDate] = useState<string>(
    certificate.issue_date ? certificate.issue_date.split('T')[0] : ''
  );
  const [receiver, setReceiver] = useState<string>(certificate.certificate_receiver || '');
  const [deliveryDate, setDeliveryDate] = useState<string>(
    certificate.delivery_date ? certificate.delivery_date.split('T')[0] : ''
  );
  const [status, setStatus] = useState<number>(certificate.is_active);

  const [courses, setCourses] = useState<Course[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [loadingCatalogs, setLoadingCatalogs] = useState<boolean>(true);

  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    let isMounted = true;
    setLoadingCatalogs(true);

    Promise.all([courseService.getCourses({ per_page: 100 }), batchService.getBatches({ per_page: 100 })])
      .then(([coursesRes, batchesRes]) => {
        if (!isMounted) return;
        setCourses(coursesRes.data || []);
        setBatches(batchesRes.data || []);
      })
      .catch((err) => {
        console.error('Failed to load courses & batches for certificate edit:', err);
      })
      .finally(() => {
        if (isMounted) setLoadingCatalogs(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const totalHours = Number(theoryHours || 0) + Number(practicalHours || 0) + Number(internHours || 0);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setServerError(null);
    setValidationErrors({});

    const payload: Partial<IcbdFormData> = {
      course_id: Number(courseId),
      batch_id: Number(batchId),
      certificate_no: certificateNo.trim(),
      theory_hours: Number(theoryHours || 0),
      practical_hours: Number(practicalHours || 0),
      intern_hours: Number(internHours || 0),
      result: result.trim() || undefined,
      issue_date: issueDate || undefined,
      certificate_receiver: receiver.trim() || undefined,
      delivery_date: deliveryDate || undefined,
      is_active: status,
    };

    try {
      const updated = await certificateService.updateIcbdCertificate(certificate.id, payload);
      onSuccess(updated);
      onClose();
    } catch (err: unknown) {
      const apiErr = err as {
        response?: {
          data?: {
            message?: string;
            errors?: Record<string, string[]>;
          };
        };
        message?: string;
      };

      if (apiErr?.response?.data?.errors) {
        setValidationErrors(apiErr.response.data.errors);
        setServerError('Please review the errors highlighted below.');
      } else {
        setServerError(
          apiErr?.response?.data?.message || apiErr?.message || 'Failed to update certificate record.'
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
              Edit Certificate Record
            </div>
            <h2 className="innova-modal-title">
              {certificate.certificate_no} — {certificate.student?.sname || 'Student'}
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

        {/* Body */}
        <form onSubmit={handleSubmit}>
          <div className="innova-modal-body" style={{ maxHeight: '72vh', overflowY: 'auto' }}>
            {serverError && (
              <div style={{ marginBottom: '1rem' }}>
                <Alert type="error" title="Update Error">
                  {serverError}
                </Alert>
              </div>
            )}

            {/* Certificate Serial & Status */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_cert_no">
                  Certificate Serial Number <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <input
                  type="text"
                  id="edit_cert_no"
                  className="innova-form-control"
                  value={certificateNo}
                  onChange={(e) => setCertificateNo(e.target.value)}
                  required
                />
                {validationErrors.certificate_no && (
                  <span className="innova-form-error">{validationErrors.certificate_no[0]}</span>
                )}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_cert_status">
                  Lifecycle Status <span style={{ color: 'var(--innova-error)' }}>*</span>
                </label>
                <select
                  id="edit_cert_status"
                  className="innova-form-control"
                  value={status}
                  onChange={(e) => setStatus(Number(e.target.value))}
                  required
                >
                  <option value={1}>Delivered (Active)</option>
                  <option value={2}>Processing (Waiting)</option>
                  <option value={3}>Printing Queue</option>
                  <option value={4}>Revoked / Cancelled</option>
                </select>
                {validationErrors.is_active && (
                  <span className="innova-form-error">{validationErrors.is_active[0]}</span>
                )}
              </div>
            </div>

            {/* Course & Batch */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_cert_course">
                  Course Program
                </label>
                {loadingCatalogs ? (
                  <LoadingSpinner size="sm" />
                ) : (
                  <select
                    id="edit_cert_course"
                    className="innova-form-control"
                    value={courseId}
                    onChange={(e) => setCourseId(Number(e.target.value))}
                    required
                  >
                    {courses.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </select>
                )}
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_cert_batch">
                  Academic Batch
                </label>
                {loadingCatalogs ? (
                  <LoadingSpinner size="sm" />
                ) : (
                  <select
                    id="edit_cert_batch"
                    className="innova-form-control"
                    value={batchId}
                    onChange={(e) => setBatchId(Number(e.target.value))}
                    required
                  >
                    {batches.map((b) => (
                      <option key={b.id} value={b.id}>
                        {b.batchnum}
                      </option>
                    ))}
                  </select>
                )}
              </div>
            </div>

            {/* Curriculum Hours */}
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

            {/* Result & Dates */}
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: '1rem',
                marginBottom: '1rem',
              }}
            >
              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_result">
                  Academic Result / Grade
                </label>
                <input
                  type="text"
                  id="edit_result"
                  className="innova-form-control"
                  value={result}
                  onChange={(e) => setResult(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_issue_date">
                  Issue Date
                </label>
                <input
                  type="date"
                  id="edit_issue_date"
                  className="innova-form-control"
                  value={issueDate}
                  onChange={(e) => setIssueDate(e.target.value)}
                />
              </div>

              <div className="innova-form-group">
                <label className="innova-form-label" htmlFor="edit_delivery_date">
                  Delivery Date
                </label>
                <input
                  type="date"
                  id="edit_delivery_date"
                  className="innova-form-control"
                  value={deliveryDate}
                  onChange={(e) => setDeliveryDate(e.target.value)}
                />
              </div>
            </div>

            {/* Receiver Name */}
            <div className="innova-form-group">
              <label className="innova-form-label" htmlFor="edit_receiver">
                Certificate Receiver Name
              </label>
              <input
                type="text"
                id="edit_receiver"
                className="innova-form-control"
                placeholder="Recipient name if handed over"
                value={receiver}
                onChange={(e) => setReceiver(e.target.value)}
              />
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
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <LoadingSpinner size="sm" />
                  <span>Saving Changes...</span>
                </>
              ) : (
                'Update Certificate'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

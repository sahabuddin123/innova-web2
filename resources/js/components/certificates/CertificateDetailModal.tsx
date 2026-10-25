import React, { useState, useEffect } from 'react';
import { CertificateIcbd, ICBD_STATUS_MAP } from '../../types';
import { certificateService } from '../../services';
import { LoadingSpinner } from '../common';

interface CertificateDetailModalProps {
  certificate: CertificateIcbd | null;
  isOpen: boolean;
  onClose: () => void;
  onEdit?: (cert: CertificateIcbd) => void;
  onUpdateStatus?: (cert: CertificateIcbd) => void;
}

export const CertificateDetailModal: React.FC<CertificateDetailModalProps> = ({
  certificate,
  isOpen,
  onClose,
  onEdit,
  onUpdateStatus,
}) => {
  if (!isOpen || !certificate) return null;

  const [qrDataUri, setQrDataUri] = useState<string | null>(null);
  const [loadingQr, setLoadingQr] = useState<boolean>(false);

  useEffect(() => {
    let isMounted = true;
    if (certificate.id) {
      setLoadingQr(true);
      certificateService
        .getIcbdQr(certificate.id)
        .then((res) => {
          if (isMounted) setQrDataUri(res.qr_data_uri);
        })
        .catch(() => {
          // Gracefully fall back to text label if QR generation is unavailable
        })
        .finally(() => {
          if (isMounted) setLoadingQr(false);
        });
    }
    return () => {
      isMounted = false;
    };
  }, [certificate.id]);

  const statusMeta = ICBD_STATUS_MAP[certificate.is_active] || {
    label: certificate.status_label || 'Unknown',
    badgeClass: 'innova-badge-gray',
    color: 'var(--innova-gray-600)',
  };

  const totalHours =
    certificate.total_hours !== undefined
      ? certificate.total_hours
      : (certificate.theory_hours || 0) +
        (certificate.practical_hours || 0) +
        (certificate.intern_hours || 0);

  const printUrl = `/api/v1/certificates/${certificate.id}/print`;
  const pdfUrl = `/api/v1/certificates/${certificate.id}/pdf`;
  const verifyUrl = `/certifiocate-verify/${certificate.certificate_no}`;

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="innova-modal"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '780px', width: '95%' }}
      >
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Institutional Certificate Details
            </div>
            <h2 className="innova-modal-title" style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <span>{certificate.certificate_no}</span>
              <span className={`innova-badge ${statusMeta.badgeClass}`}>{statusMeta.label}</span>
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

        {/* Modal Body */}
        <div className="innova-modal-body" style={{ maxHeight: '75vh', overflowY: 'auto' }}>
          {/* Main Grid: Student & Course Info */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
              gap: '1.25rem',
              marginBottom: '1.25rem',
            }}
          >
            {/* Student Card */}
            <div
              style={{
                background: 'var(--innova-gray-50)',
                padding: '1rem',
                borderRadius: '8px',
                border: '1px solid var(--innova-gray-200)',
              }}
            >
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--innova-gray-500)', textTransform: 'uppercase', marginBottom: '0.5rem' }}>
                Candidate Information
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.75rem' }}>
                <div
                  style={{
                    width: '48px',
                    height: '48px',
                    borderRadius: '50%',
                    background: 'var(--innova-primary-light, #e0e7ff)',
                    color: 'var(--innova-primary)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontWeight: 700,
                    fontSize: '1.1rem',
                    flexShrink: 0,
                  }}
                >
                  {certificate.student?.sname?.charAt(0).toUpperCase() || 'S'}
                </div>
                <div>
                  <h4 style={{ margin: 0, fontSize: '1rem', fontWeight: 600 }}>
                    {certificate.student?.sname || 'Student Record'}
                  </h4>
                  <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                    ID: #{certificate.student_id} {certificate.student?.phone ? `• ${certificate.student.phone}` : ''}
                  </div>
                </div>
              </div>

              {certificate.student?.nid && (
                <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)', marginBottom: '0.25rem' }}>
                  <strong>NID:</strong> {certificate.student.nid}
                </div>
              )}
              {certificate.certificate_receiver && (
                <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                  <strong>Receiver:</strong> {certificate.certificate_receiver}
                </div>
              )}
            </div>

            {/* Course & Batch Card */}
            <div
              style={{
                background: 'var(--innova-gray-50)',
                padding: '1rem',
                borderRadius: '8px',
                border: '1px solid var(--innova-gray-200)',
              }}
            >
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--innova-gray-500)', textTransform: 'uppercase', marginBottom: '0.5rem' }}>
                Academic Program
              </div>
              <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '1rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                {certificate.course?.name || `Course #${certificate.course_id}`}
              </h4>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.35rem', fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
                <div>
                  <strong>Batch:</strong> {certificate.batch?.batchnum || `Batch #${certificate.batch_id}`}
                </div>
                {certificate.course?.courseduration && (
                  <div>
                    <strong>Duration:</strong> {certificate.course.courseduration} Months
                  </div>
                )}
                <div>
                  <strong>Result / Grade:</strong>{' '}
                  <span style={{ fontWeight: 600, color: 'var(--innova-primary)' }}>
                    {certificate.result || 'Successful Completion'}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Curriculum Hours Breakdown */}
          <div
            style={{
              background: '#fff',
              border: '1px solid var(--innova-gray-200)',
              borderRadius: '8px',
              padding: '1rem',
              marginBottom: '1.25rem',
            }}
          >
            <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--innova-gray-500)', textTransform: 'uppercase', marginBottom: '0.75rem' }}>
              Curriculum Hours Breakdown
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '0.75rem', textAlign: 'center' }}>
              <div style={{ padding: '0.5rem', background: 'var(--innova-gray-50)', borderRadius: '6px' }}>
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>Classroom Theory</div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--innova-gray-900)' }}>
                  {certificate.theory_hours || 0}h
                </div>
              </div>
              <div style={{ padding: '0.5rem', background: 'var(--innova-gray-50)', borderRadius: '6px' }}>
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>Practical Lab</div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--innova-gray-900)' }}>
                  {certificate.practical_hours || 0}h
                </div>
              </div>
              <div style={{ padding: '0.5rem', background: 'var(--innova-gray-50)', borderRadius: '6px' }}>
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>Internship</div>
                <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--innova-gray-900)' }}>
                  {certificate.intern_hours || 0}h
                </div>
              </div>
              <div style={{ padding: '0.5rem', background: 'var(--innova-primary-light, #eff6ff)', borderRadius: '6px', border: '1px solid var(--innova-primary, #3b82f6)' }}>
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-primary)', fontWeight: 600 }}>Total Curriculum</div>
                <div style={{ fontSize: '1.25rem', fontWeight: 800, color: 'var(--innova-primary)' }}>
                  {totalHours}h
                </div>
              </div>
            </div>
          </div>

          {/* Dates & QR Section */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))',
              gap: '1.25rem',
              alignItems: 'center',
            }}
          >
            <div>
              <div style={{ fontSize: '0.75rem', fontWeight: 700, color: 'var(--innova-gray-500)', textTransform: 'uppercase', marginBottom: '0.5rem' }}>
                Key Lifecycle Dates
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '0.4rem', fontSize: '0.85rem' }}>
                <div>
                  <span style={{ color: 'var(--innova-gray-500)' }}>Issue Date:</span>{' '}
                  <strong>{certificate.issue_date ? new Date(certificate.issue_date).toLocaleDateString() : 'Not Set'}</strong>
                </div>
                <div>
                  <span style={{ color: 'var(--innova-gray-500)' }}>Delivery Date:</span>{' '}
                  <strong>{certificate.delivery_date ? new Date(certificate.delivery_date).toLocaleDateString() : 'Pending'}</strong>
                </div>
                <div>
                  <span style={{ color: 'var(--innova-gray-500)' }}>Record Created:</span>{' '}
                  <span>{certificate.created_at ? new Date(certificate.created_at).toLocaleDateString() : 'N/A'}</span>
                </div>
              </div>
            </div>

            {/* QR Code Verification Preview */}
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '1rem',
                padding: '0.75rem',
                border: '1px dashed var(--innova-gray-300)',
                borderRadius: '8px',
                background: 'var(--innova-gray-50)',
              }}
            >
              <div style={{ width: '80px', height: '80px', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#fff', borderRadius: '4px' }}>
                {loadingQr ? (
                  <LoadingSpinner size="sm" />
                ) : qrDataUri ? (
                  <img src={qrDataUri} alt="Verification QR" style={{ width: '74px', height: '74px' }} />
                ) : (
                  <span style={{ fontSize: '0.7rem', color: 'var(--innova-gray-400)' }}>QR</span>
                )}
              </div>
              <div style={{ fontSize: '0.8rem' }}>
                <div style={{ fontWeight: 600, color: 'var(--innova-gray-800)', marginBottom: '0.2rem' }}>
                  Anti-Tamper QR Code
                </div>
                <div style={{ color: 'var(--innova-gray-500)', marginBottom: '0.4rem', fontSize: '0.75rem' }}>
                  Resolves to public authenticity portal
                </div>
                <a
                  href={verifyUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="innova-btn innova-btn-sm innova-btn-secondary"
                  style={{ textDecoration: 'none', display: 'inline-flex', padding: '0.2rem 0.5rem', fontSize: '0.75rem' }}
                >
                  Verify Online &rarr;
                </a>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Footer with Actions */}
        <div
          className="innova-modal-footer"
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            flexWrap: 'wrap',
            gap: '0.5rem',
          }}
        >
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <a
              href={printUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="innova-btn innova-btn-secondary"
              style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <polyline points="6 9 6 2 18 2 18 9" />
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                <rect x="6" y="14" width="12" height="8" />
              </svg>
              Print Certificate
            </a>
            <a
              href={pdfUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="innova-btn innova-btn-secondary"
              style={{ textDecoration: 'none', display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
            >
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
              </svg>
              Download PDF
            </a>
          </div>

          <div style={{ display: 'flex', gap: '0.5rem' }}>
            {onUpdateStatus && (
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={() => onUpdateStatus(certificate)}
              >
                Change Status
              </button>
            )}
            {onEdit && (
              <button
                type="button"
                className="innova-btn innova-btn-primary"
                onClick={() => onEdit(certificate)}
              >
                Edit Certificate
              </button>
            )}
            <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

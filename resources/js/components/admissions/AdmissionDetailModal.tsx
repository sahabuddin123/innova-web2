import React, { useState, useEffect } from 'react';
import { Admission, ADMISSION_STATUS_MAP, COURSE_TYPE_MAP } from '../../types';
import { admissionService } from '../../services';

interface AdmissionDetailModalProps {
  admission: Admission | null;
  isOpen: boolean;
  onClose: () => void;
  onEdit?: (admission: Admission) => void;
  onChangeStatus?: (admission: Admission) => void;
  onEnroll?: (admission: Admission) => void;
  canEdit?: boolean;
}

export const AdmissionDetailModal: React.FC<AdmissionDetailModalProps> = ({
  admission,
  isOpen,
  onClose,
  onEdit,
  onChangeStatus,
  onEnroll,
  canEdit = false,
}) => {
  const [duplicateCheckRunning, setDuplicateCheckRunning] = useState<boolean>(false);
  const [alreadyEnrolled, setAlreadyEnrolled] = useState<boolean | null>(null);

  useEffect(() => {
    if (isOpen && admission?.phone) {
      setDuplicateCheckRunning(true);
      admissionService
        .checkExistingStudent(admission.phone)
        .then((exists) => setAlreadyEnrolled(exists))
        .catch(() => setAlreadyEnrolled(false))
        .finally(() => setDuplicateCheckRunning(false));
    } else {
      setAlreadyEnrolled(null);
    }
  }, [isOpen, admission?.phone]);

  if (!isOpen || !admission) return null;

  const statusMeta = ADMISSION_STATUS_MAP[admission.isActive] || {
    code: admission.isActive,
    label: `Status ${admission.isActive}`,
    badgeClass: 'innova-badge-gray',
    description: '',
  };

  const courseTypeMeta = COURSE_TYPE_MAP[admission.courseType] || {
    label: admission.courseType === 1 ? 'Online' : 'Offline',
    badgeClass: 'innova-badge-gray',
  };

  // Avatar initials
  const initials = admission.username
    .split(' ')
    .filter(Boolean)
    .map((n) => n[0])
    .join('')
    .substring(0, 2)
    .toUpperCase();

  const formattedDate = admission.created_at
    ? new Date(admission.created_at).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    : 'N/A';

  const courseFee = admission.course?.coursefee
    ? `৳ ${Number(admission.course.coursefee).toLocaleString()}`
    : 'Standard Fee';

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="innova-modal"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '680px' }}
      >
        <div className="innova-modal-header">
          <div>
            <h2 className="innova-modal-title">Admission Inquiry #{admission.id}</h2>
            <p className="innova-modal-subtitle">
              Submitted on {formattedDate}
            </p>
          </div>
          <button
            type="button"
            className="innova-modal-close"
            onClick={onClose}
            aria-label="Close modal"
          >
            &times;
          </button>
        </div>

        <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {/* Header Card */}
          <div
            style={{
              display: 'flex',
              alignItems: 'center',
              gap: '1rem',
              padding: '1rem',
              backgroundColor: 'var(--innova-gray-50)',
              borderRadius: 'var(--innova-radius-md)',
              border: '1px solid var(--innova-gray-200)',
            }}
          >
            <div
              style={{
                width: '56px',
                height: '56px',
                borderRadius: '50%',
                backgroundColor: 'var(--innova-primary-100)',
                color: 'var(--innova-primary-700)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '1.25rem',
                fontWeight: 700,
                flexShrink: 0,
              }}
            >
              {initials || 'ST'}
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
                <h3 style={{ margin: 0, fontSize: '1.2rem', fontWeight: 700, color: 'var(--innova-gray-900)' }}>
                  {admission.username}
                </h3>
                <span className={`innova-badge ${statusMeta.badgeClass}`}>
                  {statusMeta.label}
                </span>
                <span className={`innova-badge ${courseTypeMeta.badgeClass}`}>
                  {courseTypeMeta.label}
                </span>
              </div>
              <p style={{ margin: '0.25rem 0 0 0', fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
                Application Ref: <strong>#ADM-{admission.id}</strong>
              </p>
            </div>
          </div>

          {/* Duplicate Enrollment Guard Banner */}
          {duplicateCheckRunning ? (
            <div
              style={{
                padding: '0.65rem 0.9rem',
                backgroundColor: 'var(--innova-gray-100)',
                borderRadius: 'var(--innova-radius-sm)',
                fontSize: '0.85rem',
                color: 'var(--innova-gray-600)',
              }}
            >
              Checking Student Directory for existing phone record...
            </div>
          ) : alreadyEnrolled ? (
            <div
              style={{
                padding: '0.75rem 1rem',
                backgroundColor: '#fffbeb',
                border: '1px solid #fde68a',
                borderRadius: 'var(--innova-radius-md)',
                display: 'flex',
                alignItems: 'center',
                gap: '0.75rem',
                color: '#92400e',
              }}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <div style={{ fontSize: '0.85rem' }}>
                <strong>Duplicate Record Safeguard:</strong> A student with phone <code>{admission.phone}</code> already exists in the Student Directory. Accepting this inquiry should not create a duplicate student profile.
              </div>
            </div>
          ) : null}

          {/* Details Grid */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
              gap: '1rem',
            }}
          >
            {/* Phone */}
            <div style={{ padding: '0.75rem', backgroundColor: '#ffffff', border: '1px solid var(--innova-gray-200)', borderRadius: 'var(--innova-radius-sm)' }}>
              <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-500)', fontWeight: 600 }}>
                Phone Number
              </div>
              <div style={{ marginTop: '0.25rem', fontSize: '1rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                <a
                  href={`tel:${admission.phone}`}
                  style={{ color: 'var(--innova-primary-600)', textDecoration: 'none' }}
                >
                  {admission.phone}
                </a>
              </div>
            </div>

            {/* Email */}
            <div style={{ padding: '0.75rem', backgroundColor: '#ffffff', border: '1px solid var(--innova-gray-200)', borderRadius: 'var(--innova-radius-sm)' }}>
              <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-500)', fontWeight: 600 }}>
                Email Address
              </div>
              <div style={{ marginTop: '0.25rem', fontSize: '0.95rem', color: 'var(--innova-gray-800)' }}>
                {admission.email ? (
                  <a
                    href={`mailto:${admission.email}`}
                    style={{ color: 'var(--innova-primary-600)', textDecoration: 'none' }}
                  >
                    {admission.email}
                  </a>
                ) : (
                  <span style={{ color: 'var(--innova-gray-400)', fontStyle: 'italic' }}>Not provided</span>
                )}
              </div>
            </div>

            {/* Course */}
            <div style={{ padding: '0.75rem', backgroundColor: '#ffffff', border: '1px solid var(--innova-gray-200)', borderRadius: 'var(--innova-radius-sm)' }}>
              <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-500)', fontWeight: 600 }}>
                Requested Course
              </div>
              <div style={{ marginTop: '0.25rem', fontSize: '0.95rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                {admission.course?.name || `Course #${admission.courseId}`}
                {admission.course?.shortTitle && (
                  <span style={{ marginLeft: '0.35rem', fontSize: '0.8rem', color: 'var(--innova-gray-500)' }}>
                    ({admission.course.shortTitle})
                  </span>
                )}
              </div>
            </div>

            {/* Fee */}
            <div style={{ padding: '0.75rem', backgroundColor: '#ffffff', border: '1px solid var(--innova-gray-200)', borderRadius: 'var(--innova-radius-sm)' }}>
              <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-500)', fontWeight: 600 }}>
                Course Tuition Fee
              </div>
              <div style={{ marginTop: '0.25rem', fontSize: '1rem', fontWeight: 700, color: 'var(--innova-primary-700)' }}>
                {courseFee}
              </div>
            </div>

            {/* Delivery Mode */}
            <div style={{ padding: '0.75rem', backgroundColor: '#ffffff', border: '1px solid var(--innova-gray-200)', borderRadius: 'var(--innova-radius-sm)' }}>
              <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-500)', fontWeight: 600 }}>
                Delivery Format
              </div>
              <div style={{ marginTop: '0.25rem', fontSize: '0.95rem', fontWeight: 500, color: 'var(--innova-gray-800)' }}>
                {admission.courseType === 1 ? 'Online (Remote Live Video)' : 'Offline (In-Person Classroom)'}
              </div>
            </div>

            {/* Status Description */}
            <div style={{ padding: '0.75rem', backgroundColor: '#ffffff', border: '1px solid var(--innova-gray-200)', borderRadius: 'var(--innova-radius-sm)' }}>
              <div style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-500)', fontWeight: 600 }}>
                Lifecycle State
              </div>
              <div style={{ marginTop: '0.25rem', fontSize: '0.85rem', color: 'var(--innova-gray-700)' }}>
                {statusMeta.description}
              </div>
            </div>
          </div>
        </div>

        <div className="innova-modal-footer" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            {canEdit && onChangeStatus && (
              <button
                type="button"
                className="innova-btn innova-btn-outline"
                onClick={() => onChangeStatus(admission)}
                style={{ marginRight: '0.5rem' }}
              >
                Change Status
              </button>
            )}
            {canEdit && onEdit && (
              <button
                type="button"
                className="innova-btn innova-btn-outline"
                onClick={() => onEdit(admission)}
              >
                Edit Inquiry
              </button>
            )}
          </div>

          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={onClose}
            >
              Close
            </button>
            {onEnroll && (
              <button
                type="button"
                className="innova-btn innova-btn-primary"
                onClick={() => onEnroll(admission)}
                title={alreadyEnrolled ? 'Student record already exists for this phone' : 'Enroll as Student'}
              >
                {alreadyEnrolled ? 'View Student Record' : 'Enroll as Student'}
              </button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

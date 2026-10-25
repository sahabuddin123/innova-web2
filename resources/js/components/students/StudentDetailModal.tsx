import React from 'react';
import { Student, STUDENT_STATUS_MAP } from '../../types';

interface StudentDetailModalProps {
  student: Student | null;
  isOpen: boolean;
  onClose: () => void;
  onEdit?: (student: Student) => void;
  onChangeStatus?: (student: Student) => void;
  canEdit?: boolean;
}

export const StudentDetailModal: React.FC<StudentDetailModalProps> = ({
  student,
  isOpen,
  onClose,
  onEdit,
  onChangeStatus,
  canEdit = false,
}) => {
  if (!isOpen || !student) return null;

  const statusMeta = STUDENT_STATUS_MAP[student.IsActive] || {
    label: `Status ${student.IsActive}`,
    badge: 'innova-badge-gray',
    description: '',
  };

  const formattedFee = Number(student.course_fee || 0).toLocaleString();
  const formattedBalance = Number(student.balance || 0).toLocaleString();

  // If latestPayment exists, calculate due
  const paidAmount = student.latestPayment ? Number(student.latestPayment.paidamount || student.latestPayment.amount || 0) : 0;
  const calculatedDue = Math.max(0, Number(student.balance || 0) - paidAmount);

  // Avatar initials
  const initials = student.sname
    .split(' ')
    .map((n) => n[0])
    .join('')
    .substring(0, 2)
    .toUpperCase();

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div
        className="innova-modal innova-modal-lg"
        onClick={(e) => e.stopPropagation()}
        style={{ maxWidth: '820px' }}
      >
        {/* Header */}
        <div className="innova-modal-header">
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <div
              style={{
                width: '46px',
                height: '46px',
                borderRadius: '50%',
                backgroundColor: 'var(--innova-primary-light, #e0e7ff)',
                color: 'var(--innova-primary, #4338ca)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 700,
                fontSize: '1.1rem',
                overflow: 'hidden',
                flexShrink: 0,
              }}
            >
              {student.image ? (
                <img
                  src={`/storage/${student.image}`}
                  alt={student.sname}
                  style={{ width: '100%', height: '100%', objectFit: 'cover' }}
                  onError={(e) => {
                    // Fallback to initials if image doesn't load
                    (e.target as HTMLElement).style.display = 'none';
                  }}
                />
              ) : (
                initials
              )}
            </div>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.2rem' }}>
                <span className="innova-badge innova-badge-primary">SID #{student.id}</span>
                <span className={`innova-badge ${statusMeta.badge}`}>{statusMeta.label}</span>
                {student.IsRegister === 1 && (
                  <span className="innova-badge innova-badge-success">Registered</span>
                )}
              </div>
              <h2 className="innova-modal-title">{student.sname}</h2>
            </div>
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
        <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {/* Quick Stats Grid */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
              gap: '0.75rem',
            }}
          >
            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Total Fee</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem', color: 'var(--innova-primary)' }}>
                {formattedFee} BDT
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Discount</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem', color: 'var(--innova-success)' }}>
                {student.discount ? `${student.discount} BDT` : '0 BDT'}
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Net Balance</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem', color: 'var(--innova-gray-900)' }}>
                {formattedBalance} BDT
              </div>
            </div>

            {student.latestPayment && (
              <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
                <div className="innova-stat-label">Current Due</div>
                <div
                  className="innova-stat-value"
                  style={{
                    fontSize: '1.1rem',
                    color: calculatedDue > 0 ? 'var(--innova-error)' : 'var(--innova-success)',
                  }}
                >
                  {calculatedDue.toLocaleString()} BDT
                </div>
              </div>
            )}
          </div>

          {/* Academic Enrollment Section */}
          <div
            className="innova-card"
            style={{
              padding: '1rem',
              border: '1px solid var(--innova-gray-200)',
              backgroundColor: 'var(--innova-card-bg)',
            }}
          >
            <h4 style={{ margin: '0 0 0.75rem 0', fontSize: '0.95rem', color: 'var(--innova-gray-800)' }}>
              🎓 Academic Enrollment
            </h4>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '0.75rem',
                fontSize: '0.875rem',
              }}
            >
              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Enrolled Course
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>
                  {student.course?.name || `Course #${student.course_id}`}
                </strong>
                {student.course?.shortTitle && (
                  <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', display: 'block' }}>
                    Code: {student.course.shortTitle}
                  </span>
                )}
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Assigned Batch
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>
                  {student.batch?.batchnum || `Batch #${student.batch_id}`}
                </strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Admission Date
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>
                  {student.admission_date
                    ? new Date(student.admission_date).toLocaleDateString(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                      })
                    : 'N/A'}
                </strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Registration Status
                </span>
                <strong style={{ color: student.IsRegister === 1 ? 'var(--innova-success)' : 'var(--innova-gray-600)' }}>
                  {student.IsRegister === 1 ? 'Registered (Board/Cert)' : 'Not Registered'}
                </strong>
              </div>
            </div>
          </div>

          {/* Personal & Contact Information */}
          <div
            className="innova-card"
            style={{
              padding: '1rem',
              border: '1px solid var(--innova-gray-200)',
              backgroundColor: 'var(--innova-card-bg)',
            }}
          >
            <h4 style={{ margin: '0 0 0.75rem 0', fontSize: '0.95rem', color: 'var(--innova-gray-800)' }}>
              👤 Personal & Contact Profile
            </h4>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: '0.75rem',
                fontSize: '0.875rem',
              }}
            >
              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Mobile Phone
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>
                  {student.phone ? `+88 ${student.phone}` : 'Not Provided'}
                </strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Gender
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>{student.gender || 'N/A'}</strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Religion
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>{student.religion || 'N/A'}</strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Date of Birth
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>
                  {student.dob ? new Date(student.dob).toLocaleDateString() : 'N/A'}
                </strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Blood Group
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>{student.bloodgroup || 'N/A'}</strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  National ID (NID)
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>{student.nid || 'N/A'}</strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Previous Institution
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>{student.last_school || 'N/A'}</strong>
              </div>

              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Reference Source
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>{student.ref || 'Direct / None'}</strong>
              </div>
            </div>
          </div>

          {/* Guardian & Family Information */}
          <div
            className="innova-card"
            style={{
              padding: '1rem',
              border: '1px solid var(--innova-gray-200)',
              backgroundColor: 'var(--innova-card-bg)',
            }}
          >
            <h4 style={{ margin: '0 0 0.75rem 0', fontSize: '0.95rem', color: 'var(--innova-gray-800)' }}>
              👨‍👩‍👧 Family & Guardian Contacts
            </h4>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
                gap: '0.75rem',
                fontSize: '0.875rem',
              }}
            >
              <div>
                <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                  Primary Guardian
                </span>
                <strong style={{ color: 'var(--innova-gray-900)' }}>
                  {student.g_name} ({student.rel_g})
                </strong>
                <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                  Phone: {student.g_phone}
                </div>
              </div>

              {student.f_name && (
                <div>
                  <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                    Father's Name
                  </span>
                  <strong style={{ color: 'var(--innova-gray-900)' }}>{student.f_name}</strong>
                  {student.f_phone && (
                    <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                      Phone: {student.f_phone}
                    </div>
                  )}
                </div>
              )}

              {student.m_name && (
                <div>
                  <span style={{ color: 'var(--innova-gray-500)', display: 'block', fontSize: '0.75rem' }}>
                    Mother's Name
                  </span>
                  <strong style={{ color: 'var(--innova-gray-900)' }}>{student.m_name}</strong>
                  {student.m_phone && (
                    <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                      Phone: {student.m_phone}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Footer */}
        <div className="innova-modal-footer">
          {canEdit && onChangeStatus && (
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={() => {
                onClose();
                onChangeStatus(student);
              }}
            >
              Update Status
            </button>
          )}

          {canEdit && onEdit && (
            <button
              type="button"
              className="innova-btn innova-btn-primary"
              onClick={() => {
                onClose();
                onEdit(student);
              }}
            >
              Edit Student
            </button>
          )}

          <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

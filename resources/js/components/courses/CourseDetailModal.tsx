import React from 'react';
import { Course } from '../../types';

interface CourseDetailModalProps {
  isOpen: boolean;
  onClose: () => void;
  course: Course | null;
  onEdit?: (course: Course) => void;
  canEdit?: boolean;
}

export const CourseDetailModal: React.FC<CourseDetailModalProps> = ({
  isOpen,
  onClose,
  course,
  onEdit,
  canEdit = false,
}) => {
  if (!isOpen || !course) return null;

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div className="innova-modal innova-modal-lg" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="innova-modal-header">
          <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
              <span className={`innova-badge ${course.IsActive === 1 ? 'innova-badge-success' : 'innova-badge-error'}`}>
                {course.IsActive === 1 ? 'Active' : 'Deactivated'}
              </span>
              {course.featured === 1 && (
                <span className="innova-badge innova-badge-primary">Featured</span>
              )}
              {course.shortTitle && (
                <span className="innova-badge innova-badge-gray">{course.shortTitle}</span>
              )}
            </div>
            <h2 className="innova-modal-title">{course.name}</h2>
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
        <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
          {/* Quick Metrics Grid */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(130px, 1fr))', gap: '0.75rem' }}>
            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Course Fee</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem', color: 'var(--innova-primary)' }}>
                {course.coursefee} BDT
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Duration</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem' }}>
                {course.courseduration ? `${course.courseduration} Months` : 'N/A'}
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Modules</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem' }}>
                {course.totalmoduls || '0'}
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Lectures</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem' }}>
                {course.lacture || '0'}
              </div>
            </div>

            <div className="innova-stat-box" style={{ padding: '0.75rem 1rem' }}>
              <div className="innova-stat-label">Projects</div>
              <div className="innova-stat-value" style={{ fontSize: '1.1rem' }}>
                {course.totalproject || '0'}
              </div>
            </div>
          </div>

          {/* Academic Info */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '1rem' }}>
            <div>
              <div style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Category
              </div>
              <div style={{ fontSize: '0.95rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                {(course.course_category || course.CourseCategory)?.CourseCategoris || 'Uncategorized'}
              </div>
            </div>

            <div>
              <div style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Lead Instructor
              </div>
              <div style={{ fontSize: '0.95rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                {course.Instructor || 'Not Assigned'}
              </div>
            </div>

            <div>
              <div style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Special Discount
              </div>
              <div style={{ fontSize: '0.95rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                {course.Discount ? `${course.Discount} BDT` : 'None'}
              </div>
            </div>

            <div>
              <div style={{ fontSize: '0.75rem', fontWeight: 600, color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Software & Tools
              </div>
              <div style={{ fontSize: '0.95rem', color: 'var(--innova-gray-800)' }}>
                {course.software || 'None Specified'}
              </div>
            </div>
          </div>

          {/* Summary / Description */}
          {course.description && (
            <div>
              <h3 style={{ fontSize: '0.9rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-700)' }}>
                Summary
              </h3>
              <p style={{ margin: 0, fontSize: '0.875rem', lineHeight: 1.6, color: 'var(--innova-gray-600)' }}>
                {course.description}
              </p>
            </div>
          )}

          {/* Detailed Overview */}
          {course.overview && (
            <div>
              <h3 style={{ fontSize: '0.9rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-700)' }}>
                Course Overview
              </h3>
              <div style={{ fontSize: '0.875rem', lineHeight: 1.6, color: 'var(--innova-gray-600)', whiteSpace: 'pre-wrap' }}>
                {course.overview}
              </div>
            </div>
          )}

          {/* Curriculum */}
          {course.curriculum && (
            <div>
              <h3 style={{ fontSize: '0.9rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-700)' }}>
                Curriculum Syllabus
              </h3>
              <div style={{ fontSize: '0.875rem', lineHeight: 1.6, color: 'var(--innova-gray-600)', whiteSpace: 'pre-wrap' }}>
                {course.curriculum}
              </div>
            </div>
          )}

          {/* Career & Placements */}
          {(course.jobPosition || course.jobPlacement || course.trainee) && (
            <div style={{ padding: '0.75rem 1rem', background: 'var(--innova-gray-50)', borderRadius: 'var(--innova-radius-md)', border: '1px solid var(--innova-gray-200)' }}>
              <h3 style={{ fontSize: '0.9rem', fontWeight: 700, margin: '0 0 0.5rem 0', color: 'var(--innova-gray-800)' }}>
                Career & Trainee Profile
              </h3>
              {course.jobPosition && (
                <div style={{ fontSize: '0.85rem', marginBottom: '0.25rem' }}>
                  <strong>Target Roles:</strong> {course.jobPosition}
                </div>
              )}
              {course.trainee && (
                <div style={{ fontSize: '0.85rem', marginBottom: '0.25rem' }}>
                  <strong>Target Audience:</strong> {course.trainee}
                </div>
              )}
              {course.jobPlacement && (
                <div style={{ fontSize: '0.85rem' }}>
                  <strong>Placement Support:</strong> {course.jobPlacement}
                </div>
              )}
            </div>
          )}

          {/* Metadata */}
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--innova-gray-400)', paddingTop: '0.5rem', borderTop: '1px solid var(--innova-gray-200)' }}>
            <span>Created: {course.created_at ? new Date(course.created_at).toLocaleDateString() : 'N/A'}</span>
            <span>Last Updated: {course.updated_at ? new Date(course.updated_at).toLocaleDateString() : 'N/A'}</span>
          </div>
        </div>

        {/* Footer */}
        <div className="innova-modal-footer">
          <button type="button" className="innova-btn innova-btn-secondary" onClick={onClose}>
            Close
          </button>
          {canEdit && onEdit && (
            <button
              type="button"
              className="innova-btn innova-btn-primary"
              onClick={() => {
                onClose();
                onEdit(course);
              }}
            >
              Edit Course
            </button>
          )}
        </div>
      </div>
    </div>
  );
};

import React, { useState, useEffect } from 'react';
import { Admission, AdmissionFormData, Course } from '../../types';
import { admissionService, courseService } from '../../services';
import { Alert } from '../common';

interface AdmissionFormModalProps {
  admission: Admission | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (admission: Admission) => void;
}

export const AdmissionFormModal: React.FC<AdmissionFormModalProps> = ({
  admission,
  isOpen,
  onClose,
  onSuccess,
}) => {
  const isEdit = Boolean(admission);

  const initialFormData: AdmissionFormData = {
    username: '',
    phone: '',
    email: '',
    courseId: '',
    courseType: 0,
    isActive: 0,
  };

  const [formData, setFormData] = useState<AdmissionFormData>(initialFormData);
  const [courses, setCourses] = useState<Course[]>([]);
  const [isLoadingCourses, setIsLoadingCourses] = useState<boolean>(false);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [formError, setFormError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  // Populate data when modal opens
  useEffect(() => {
    if (isOpen) {
      setFormError(null);
      setFieldErrors({});

      if (admission) {
        setFormData({
          username: admission.username || '',
          phone: admission.phone || '',
          email: admission.email || '',
          courseId: admission.courseId || '',
          courseType: admission.courseType ?? 0,
          isActive: admission.isActive ?? 0,
        });
      } else {
        setFormData(initialFormData);
      }

      // Fetch courses for dropdown
      setIsLoadingCourses(true);
      courseService
        .getCourses()
        .then((res) => {
          setCourses(res.data || []);
        })
        .catch(() => {
          // Fail gracefully
        })
        .finally(() => {
          setIsLoadingCourses(false);
        });
    }
  }, [isOpen, admission]);

  if (!isOpen) return null;

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]:
        name === 'courseId' || name === 'courseType' || name === 'isActive'
          ? value === ''
            ? ''
            : Number(value)
          : value,
    }));

    // Clear error for edited field
    if (fieldErrors[name]) {
      setFieldErrors((prev) => {
        const updated = { ...prev };
        delete updated[name];
        return updated;
      });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);
    setFieldErrors({});

    // Basic client validation
    if (!formData.username.trim()) {
      setFieldErrors((prev) => ({ ...prev, username: ['Student name is required.'] }));
      return;
    }
    if (!formData.phone.trim()) {
      setFieldErrors((prev) => ({ ...prev, phone: ['Phone number is required.'] }));
      return;
    }
    if (formData.courseId === '') {
      setFieldErrors((prev) => ({ ...prev, courseId: ['Please select a course.'] }));
      return;
    }

    setIsSubmitting(true);

    try {
      let saved: Admission;
      if (isEdit && admission) {
        saved = await admissionService.updateAdmission(admission.id, formData);
      } else {
        saved = await admissionService.submitAdmission(formData);
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

      if (apiErr.response?.data?.errors) {
        setFieldErrors(apiErr.response.data.errors);
        setFormError(apiErr.response.data.message || 'Validation failed. Please correct the highlighted errors.');
      } else {
        setFormError(
          apiErr.response?.data?.message ||
            apiErr.message ||
            'An unexpected error occurred while saving the admission record.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div className="innova-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '600px' }}>
        <div className="innova-modal-header">
          <div>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Online Admissions
            </div>
            <h2 className="innova-modal-title">
              {isEdit ? `Edit Admission #${admission?.id}` : 'Create New Admission Inquiry'}
            </h2>
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

        <form onSubmit={handleSubmit}>
          <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
            {formError && (
              <Alert type="error" title="Submission Notice">
                {formError}
              </Alert>
            )}

            {/* Applicant Name */}
            <div className="innova-form-group">
              <label htmlFor="adm_username" className="innova-form-label">
                Student Full Name <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
              </label>
              <input
                type="text"
                id="adm_username"
                name="username"
                className={`innova-form-input ${fieldErrors.username ? 'is-invalid' : ''}`}
                value={formData.username}
                onChange={handleChange}
                placeholder="Enter student full name"
                disabled={isSubmitting}
                required
              />
              {fieldErrors.username && (
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                  {fieldErrors.username[0]}
                </div>
              )}
            </div>

            {/* Phone & Email Row */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div className="innova-form-group">
                <label htmlFor="adm_phone" className="innova-form-label">
                  Phone Number <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
                </label>
                <input
                  type="tel"
                  id="adm_phone"
                  name="phone"
                  className={`innova-form-input ${fieldErrors.phone ? 'is-invalid' : ''}`}
                  value={formData.phone}
                  onChange={handleChange}
                  placeholder="e.g. 01700000000"
                  disabled={isSubmitting}
                  required
                />
                {fieldErrors.phone && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                    {fieldErrors.phone[0]}
                  </div>
                )}
              </div>

              <div className="innova-form-group">
                <label htmlFor="adm_email" className="innova-form-label">
                  Email Address <span style={{ color: 'var(--innova-gray-400)', fontWeight: 400 }}>(Optional)</span>
                </label>
                <input
                  type="email"
                  id="adm_email"
                  name="email"
                  className={`innova-form-input ${fieldErrors.email ? 'is-invalid' : ''}`}
                  value={formData.email}
                  onChange={handleChange}
                  placeholder="student@example.com"
                  disabled={isSubmitting}
                />
                {fieldErrors.email && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                    {fieldErrors.email[0]}
                  </div>
                )}
              </div>
            </div>

            {/* Course Selection */}
            <div className="innova-form-group">
              <label htmlFor="adm_courseId" className="innova-form-label">
                Course Applied For <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
              </label>
              <select
                id="adm_courseId"
                name="courseId"
                className={`innova-form-input ${fieldErrors.courseId ? 'is-invalid' : ''}`}
                value={formData.courseId}
                onChange={handleChange}
                disabled={isSubmitting || isLoadingCourses}
                required
              >
                <option value="">-- Select Course --</option>
                {courses.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name} {c.shortTitle ? `(${c.shortTitle})` : ''} — ৳ {Number(c.coursefee || 0).toLocaleString()}
                  </option>
                ))}
              </select>
              {fieldErrors.courseId && (
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                  {fieldErrors.courseId[0]}
                </div>
              )}
            </div>

            {/* Delivery Type & Status Row */}
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '1rem' }}>
              <div className="innova-form-group">
                <label htmlFor="adm_courseType" className="innova-form-label">
                  Class Format <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
                </label>
                <select
                  id="adm_courseType"
                  name="courseType"
                  className={`innova-form-input ${fieldErrors.courseType ? 'is-invalid' : ''}`}
                  value={formData.courseType}
                  onChange={handleChange}
                  disabled={isSubmitting}
                  required
                >
                  <option value={0}>Offline (In-Person Classroom)</option>
                  <option value={1}>Online (Remote Live Session)</option>
                </select>
                {fieldErrors.courseType && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                    {fieldErrors.courseType[0]}
                  </div>
                )}
              </div>

              {isEdit && (
                <div className="innova-form-group">
                  <label htmlFor="adm_isActive" className="innova-form-label">
                    Inquiry Lifecycle Status
                  </label>
                  <select
                    id="adm_isActive"
                    name="isActive"
                    className={`innova-form-input ${fieldErrors.isActive ? 'is-invalid' : ''}`}
                    value={formData.isActive}
                    onChange={handleChange}
                    disabled={isSubmitting}
                  >
                    <option value={0}>0 — Waiting for Contact</option>
                    <option value={1}>1 — Call Not Received</option>
                    <option value={2}>2 — Waiting For Admission</option>
                    <option value={3}>3 — Admission Complete</option>
                    <option value={4}>4 — SMS Sent</option>
                    <option value={5}>5 — Not Interested</option>
                    <option value={6}>6 — Restricted</option>
                  </select>
                  {fieldErrors.isActive && (
                    <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                      {fieldErrors.isActive[0]}
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>

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
              {isSubmitting
                ? isEdit
                  ? 'Saving Changes...'
                  : 'Submitting...'
                : isEdit
                ? 'Update Admission'
                : 'Submit Admission'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

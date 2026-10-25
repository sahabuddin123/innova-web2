import React, { useState, useEffect } from 'react';
import { Admission, AdmissionFormData, Course } from '../../types';
import { admissionService, courseService } from '../../services';
import { Card, Alert, LoadingSpinner } from '../common';

interface PublicAdmissionFormProps {
  onSubmitted?: (admission: Admission) => void;
  preselectedCourseId?: number;
}

export const PublicAdmissionForm: React.FC<PublicAdmissionFormProps> = ({
  onSubmitted,
  preselectedCourseId,
}) => {
  const [formData, setFormData] = useState<AdmissionFormData>({
    username: '',
    phone: '',
    email: '',
    courseId: preselectedCourseId || '',
    courseType: 0,
  });

  const [courses, setCourses] = useState<Course[]>([]);
  const [isLoadingCourses, setIsLoadingCourses] = useState<boolean>(true);
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [submittedAdmission, setSubmittedAdmission] = useState<Admission | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  useEffect(() => {
    setIsLoadingCourses(true);
    courseService
      .getCourses()
      .then((res) => {
        setCourses(res.data || []);
      })
      .catch(() => {
        // Fallback gracefully
      })
      .finally(() => {
        setIsLoadingCourses(false);
      });
  }, []);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
  ) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]:
        name === 'courseId' || name === 'courseType'
          ? value === ''
            ? ''
            : Number(value)
          : value,
    }));

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
    setErrorMessage(null);
    setFieldErrors({});

    // Client-side validation
    if (!formData.username.trim()) {
      setFieldErrors((prev) => ({ ...prev, username: ['Please enter your full name.'] }));
      return;
    }
    if (!formData.phone.trim()) {
      setFieldErrors((prev) => ({ ...prev, phone: ['Please enter your contact phone number.'] }));
      return;
    }
    if (formData.courseId === '') {
      setFieldErrors((prev) => ({ ...prev, courseId: ['Please select a course to enroll in.'] }));
      return;
    }

    setIsSubmitting(true);

    try {
      const result = await admissionService.submitAdmission(formData);
      setSubmittedAdmission(result);
      if (onSubmitted) {
        onSubmitted(result);
      }
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
        setErrorMessage(
          apiErr.response.data.message ||
            'Submission error: Please resolve the highlighted fields below.'
        );
      } else {
        setErrorMessage(
          apiErr.response?.data?.message ||
            apiErr.message ||
            'Oops, something went wrong submitting your application! Please try again.'
        );
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleReset = () => {
    setSubmittedAdmission(null);
    setFormData({
      username: '',
      phone: '',
      email: '',
      courseId: preselectedCourseId || '',
      courseType: 0,
    });
    setErrorMessage(null);
    setFieldErrors({});
  };

  if (submittedAdmission) {
    return (
      <Card
        title="Application Received Successfully!"
        subtitle="INNOVA Computers BD Online Admission Portal"
      >
        <div style={{ textAlign: 'center', padding: '1.5rem 0' }}>
          <div
            style={{
              width: '64px',
              height: '64px',
              borderRadius: '50%',
              backgroundColor: '#ecfdf5',
              color: '#059669',
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              marginBottom: '1rem',
            }}
          >
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
              <polyline points="20 6 9 17 4 12" />
            </svg>
          </div>

          <h2 style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--innova-gray-900)', margin: '0 0 0.5rem 0' }}>
            Congratulations, {submittedAdmission.username}!
          </h2>
          <p style={{ fontSize: '1rem', color: 'var(--innova-gray-600)', maxWidth: '520px', margin: '0 auto 1.5rem auto' }}>
            Your admission data has been submitted successfully under Application Reference{' '}
            <strong>#ADM-{submittedAdmission.id}</strong>. Our academic representative will contact you shortly at{' '}
            <strong>{submittedAdmission.phone}</strong>.
          </p>

          <div
            style={{
              display: 'inline-flex',
              flexDirection: 'column',
              gap: '0.5rem',
              padding: '1rem 1.5rem',
              backgroundColor: 'var(--innova-gray-50)',
              borderRadius: 'var(--innova-radius-md)',
              border: '1px solid var(--innova-gray-200)',
              textAlign: 'left',
              marginBottom: '1.5rem',
            }}
          >
            <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
              Selected Format:{' '}
              <strong>{submittedAdmission.courseType === 1 ? 'Online (Live Stream)' : 'Offline (In-Person Classroom)'}</strong>
            </div>
            <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
              Application Status:{' '}
              <span className="innova-badge innova-badge-warning">Waiting for Contact</span>
            </div>
          </div>

          <div>
            <button
              type="button"
              className="innova-btn innova-btn-outline"
              onClick={handleReset}
            >
              Submit Another Application
            </button>
          </div>
        </div>
      </Card>
    );
  }

  return (
    <Card
      title="Fill the Form Below With Correct Information"
      subtitle="After filling the form our academic representative will contact you shortly"
    >
      {errorMessage && (
        <Alert type="error" title="Submission Notice">
          {errorMessage}
        </Alert>
      )}

      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
        {/* Full Name */}
        <div className="innova-form-group">
          <label htmlFor="public_username" className="innova-form-label">
            Your Full Name <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
          </label>
          <input
            type="text"
            id="public_username"
            name="username"
            className={`innova-form-input ${fieldErrors.username ? 'is-invalid' : ''}`}
            placeholder="Please enter your full name here"
            value={formData.username}
            onChange={handleChange}
            disabled={isSubmitting}
            required
          />
          {fieldErrors.username && (
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
              {fieldErrors.username[0]}
            </div>
          )}
        </div>

        {/* Contact Phone & Email */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
          <div className="innova-form-group">
            <label htmlFor="public_phone" className="innova-form-label">
              Phone Number <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
            </label>
            <input
              type="tel"
              id="public_phone"
              name="phone"
              className={`innova-form-input ${fieldErrors.phone ? 'is-invalid' : ''}`}
              placeholder="Please enter your mobile phone number"
              value={formData.phone}
              onChange={handleChange}
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
            <label htmlFor="public_email" className="innova-form-label">
              Email Address <span style={{ color: 'var(--innova-gray-400)', fontWeight: 400 }}>(Optional)</span>
            </label>
            <input
              type="email"
              id="public_email"
              name="email"
              className={`innova-form-input ${fieldErrors.email ? 'is-invalid' : ''}`}
              placeholder="Please enter your email address here"
              value={formData.email}
              onChange={handleChange}
              disabled={isSubmitting}
            />
            {fieldErrors.email && (
              <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
                {fieldErrors.email[0]}
              </div>
            )}
          </div>
        </div>

        {/* Course Select */}
        <div className="innova-form-group">
          <label htmlFor="public_courseId" className="innova-form-label">
            Which Course Do You Want to Do? <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
          </label>
          <select
            id="public_courseId"
            name="courseId"
            className={`innova-form-input ${fieldErrors.courseId ? 'is-invalid' : ''}`}
            value={formData.courseId}
            onChange={handleChange}
            disabled={isSubmitting || isLoadingCourses}
            required
          >
            <option value="">
              {isLoadingCourses ? 'Loading available courses...' : '-- Select Course --'}
            </option>
            {courses.map((course) => (
              <option key={course.id} value={course.id}>
                {course.name} {course.shortTitle ? `(${course.shortTitle})` : ''} — ৳ {Number(course.coursefee || 0).toLocaleString()}
              </option>
            ))}
          </select>
          {fieldErrors.courseId && (
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
              {fieldErrors.courseId[0]}
            </div>
          )}
        </div>

        {/* Class Type */}
        <div className="innova-form-group">
          <label htmlFor="public_courseType" className="innova-form-label">
            Your Course Delivery Format <span style={{ color: 'var(--innova-error, #dc2626)' }}>*</span>
          </label>
          <select
            id="public_courseType"
            name="courseType"
            className={`innova-form-input ${fieldErrors.courseType ? 'is-invalid' : ''}`}
            value={formData.courseType}
            onChange={handleChange}
            disabled={isSubmitting}
            required
          >
            <option value={0}>Offline (In-Person Classroom)</option>
            <option value={1}>Online (Live Interactive Virtual Classroom)</option>
          </select>
          {fieldErrors.courseType && (
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-error, #dc2626)', marginTop: '0.25rem' }}>
              {fieldErrors.courseType[0]}
            </div>
          )}
        </div>

        {/* Submit Button with anti-duplicate safeguard */}
        <div style={{ marginTop: '0.5rem' }}>
          <button
            type="submit"
            className="innova-btn innova-btn-primary"
            style={{ width: '100%', padding: '0.85rem 1.5rem', fontSize: '1rem', fontWeight: 600 }}
            disabled={isSubmitting || isLoadingCourses}
          >
            {isSubmitting ? (
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem' }}>
                <LoadingSpinner size="sm" /> Submitting Application...
              </span>
            ) : (
              'Enroll Now'
            )}
          </button>
        </div>
      </form>
    </Card>
  );
};

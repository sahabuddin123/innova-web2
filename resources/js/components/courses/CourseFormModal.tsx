import React, { useState, useEffect } from 'react';
import { Course, CourseFormData, CourseCategory, Teacher } from '../../types';
import { courseService } from '../../services';
import { Alert, LoadingSpinner } from '../common';
import { ApiErrorResponse } from '../../types/api';

interface CourseFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (course: Course) => void;
  course?: Course | null;
  categories: CourseCategory[];
  teachers: Teacher[];
}

export const CourseFormModal: React.FC<CourseFormModalProps> = ({
  isOpen,
  onClose,
  onSuccess,
  course,
  categories,
  teachers,
}) => {
  const isEditing = !!course;
  const [activeTab, setActiveTab] = useState<'basic' | 'curriculum' | 'career' | 'settings'>('basic');
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [generalError, setGeneralError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  const [formData, setFormData] = useState<CourseFormData>({
    name: '',
    coursefee: '',
    category_id: null,
    shortTitle: '',
    courseduration: '',
    lacture: '',
    totalmoduls: '',
    totalproject: '',
    description: '',
    overview: '',
    curriculum: '',
    software: '',
    trainee: '',
    jobPlacement: '',
    jobPosition: '',
    IsActive: 1,
    featured: 0,
    IsCourse: 1,
    image: '',
    video: '',
    Instructor: '',
    Discount: '',
  });

  useEffect(() => {
    if (course) {
      setFormData({
        name: course.name || '',
        coursefee: course.coursefee || '',
        category_id: course.category_id ?? null,
        shortTitle: course.shortTitle || '',
        courseduration: course.courseduration || '',
        lacture: course.lacture || '',
        totalmoduls: course.totalmoduls || '',
        totalproject: course.totalproject || '',
        description: course.description || '',
        overview: course.overview || '',
        curriculum: course.curriculum || '',
        software: course.software || '',
        trainee: course.trainee || '',
        jobPlacement: course.jobPlacement || '',
        jobPosition: course.jobPosition || '',
        IsActive: course.IsActive ?? 1,
        featured: course.featured ?? 0,
        IsCourse: course.IsCourse ?? 1,
        image: course.image || '',
        video: course.video || '',
        Instructor: course.Instructor || '',
        Discount: course.Discount || '',
      });
    } else {
      setFormData({
        name: '',
        coursefee: '',
        category_id: categories.length > 0 ? categories[0].id : null,
        shortTitle: '',
        courseduration: '3',
        lacture: '24',
        totalmoduls: '6',
        totalproject: '2',
        description: '',
        overview: '',
        curriculum: '',
        software: '',
        trainee: '',
        jobPlacement: '',
        jobPosition: '',
        IsActive: 1,
        featured: 0,
        IsCourse: 1,
        image: '',
        video: '',
        Instructor: '',
        Discount: '',
      });
    }
    setActiveTab('basic');
    setGeneralError(null);
    setFieldErrors({});
  }, [course, isOpen, categories]);

  if (!isOpen) return null;

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    let parsedValue: unknown = value;

    if (type === 'number') {
      parsedValue = value === '' ? '' : Number(value);
    } else if (name === 'category_id' || name === 'IsActive' || name === 'featured' || name === 'IsCourse') {
      parsedValue = value === '' ? null : Number(value);
    }

    setFormData((prev) => ({
      ...prev,
      [name]: parsedValue,
    }));

    // Clear field error on change
    if (fieldErrors[name]) {
      setFieldErrors((prev) => {
        const copy = { ...prev };
        delete copy[name];
        return copy;
      });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setGeneralError(null);
    setFieldErrors({});

    try {
      let saved: Course;
      if (isEditing && course) {
        saved = await courseService.updateCourse(course.id, formData);
      } else {
        saved = await courseService.createCourse(formData);
      }
      onSuccess(saved);
      onClose();
    } catch (err) {
      const apiErr = err as ApiErrorResponse;
      setGeneralError(apiErr.message || 'Failed to save course. Please check inputs.');
      if (apiErr.errors && typeof apiErr.errors === 'object') {
        setFieldErrors(apiErr.errors as Record<string, string[]>);
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="innova-modal-backdrop" onClick={onClose} role="dialog" aria-modal="true">
      <div className="innova-modal innova-modal-lg" onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="innova-modal-header">
          <h2 className="innova-modal-title">
            {isEditing ? `Edit Course: ${course?.name}` : 'Create New Course'}
          </h2>
          <button
            type="button"
            className="innova-btn innova-btn-sm innova-btn-secondary"
            onClick={onClose}
            disabled={submitting}
            aria-label="Close"
          >
            &times;
          </button>
        </div>

        {/* Form Body */}
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', flex: 1, minHeight: 0 }}>
          <div className="innova-modal-body">
            {generalError && (
              <Alert type="error" title="Validation Error">
                {generalError}
              </Alert>
            )}

            {/* Navigation Tabs */}
            <div className="innova-tabs">
              <button
                type="button"
                className={`innova-tab ${activeTab === 'basic' ? 'innova-tab-active' : ''}`}
                onClick={() => setActiveTab('basic')}
              >
                1. Basic Info
              </button>
              <button
                type="button"
                className={`innova-tab ${activeTab === 'curriculum' ? 'innova-tab-active' : ''}`}
                onClick={() => setActiveTab('curriculum')}
              >
                2. Modules & Curriculum
              </button>
              <button
                type="button"
                className={`innova-tab ${activeTab === 'career' ? 'innova-tab-active' : ''}`}
                onClick={() => setActiveTab('career')}
              >
                3. Career & Media
              </button>
              <button
                type="button"
                className={`innova-tab ${activeTab === 'settings' ? 'innova-tab-active' : ''}`}
                onClick={() => setActiveTab('settings')}
              >
                4. Status & Settings
              </button>
            </div>

            {/* Tab 1: Basic Info */}
            {activeTab === 'basic' && (
              <div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="name">
                      Course Name <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="name"
                      name="name"
                      type="text"
                      className="innova-form-control"
                      value={formData.name}
                      onChange={handleChange}
                      placeholder="e.g. Full Stack Web Development"
                      required
                    />
                    {fieldErrors.name && <div className="innova-form-error">{fieldErrors.name[0]}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="shortTitle">
                      Short Code / Code Name
                    </label>
                    <input
                      id="shortTitle"
                      name="shortTitle"
                      type="text"
                      className="innova-form-control"
                      value={formData.shortTitle || ''}
                      onChange={handleChange}
                      placeholder="e.g. FSWD-01"
                    />
                    {fieldErrors.shortTitle && <div className="innova-form-error">{fieldErrors.shortTitle[0]}</div>}
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="category_id">
                      Academic Category
                    </label>
                    <select
                      id="category_id"
                      name="category_id"
                      className="innova-form-control"
                      value={formData.category_id ?? ''}
                      onChange={handleChange}
                    >
                      <option value="">-- Uncategorized --</option>
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.CourseCategoris}
                        </option>
                      ))}
                    </select>
                    {fieldErrors.category_id && <div className="innova-form-error">{fieldErrors.category_id[0]}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="coursefee">
                      Course Fee (BDT) <span style={{ color: 'var(--innova-error)' }}>*</span>
                    </label>
                    <input
                      id="coursefee"
                      name="coursefee"
                      type="text"
                      className="innova-form-control"
                      value={formData.coursefee}
                      onChange={handleChange}
                      placeholder="e.g. 15000"
                      required
                    />
                    {fieldErrors.coursefee && <div className="innova-form-error">{fieldErrors.coursefee[0]}</div>}
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="Discount">
                      Discount (BDT)
                    </label>
                    <input
                      id="Discount"
                      name="Discount"
                      type="number"
                      className="innova-form-control"
                      value={formData.Discount ?? ''}
                      onChange={handleChange}
                      placeholder="0"
                    />
                  </div>
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="courseduration">
                      Course Duration
                    </label>
                    <select
                      id="courseduration"
                      name="courseduration"
                      className="innova-form-control"
                      value={formData.courseduration || ''}
                      onChange={handleChange}
                    >
                      <option value="">-- Select Duration --</option>
                      <option value="1">1 Month</option>
                      <option value="2">2 Months</option>
                      <option value="3">3 Months</option>
                      <option value="4">4 Months</option>
                      <option value="6">6 Months</option>
                      <option value="8">8 Months</option>
                      <option value="12">12 Months</option>
                    </select>
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="Instructor">
                      Lead Instructor
                    </label>
                    {teachers.length > 0 ? (
                      <select
                        id="Instructor"
                        name="Instructor"
                        className="innova-form-control"
                        value={formData.Instructor || ''}
                        onChange={handleChange}
                      >
                        <option value="">-- Select Instructor --</option>
                        {teachers.map((t) => (
                          <option key={t.id} value={t.name}>
                            {t.name} {t.designation ? `(${t.designation})` : ''}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <input
                        id="Instructor"
                        name="Instructor"
                        type="text"
                        className="innova-form-control"
                        value={formData.Instructor || ''}
                        onChange={handleChange}
                        placeholder="Instructor Name"
                      />
                    )}
                  </div>
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="description">
                    Summary / Short Description
                  </label>
                  <textarea
                    id="description"
                    name="description"
                    rows={3}
                    className="innova-form-control"
                    value={formData.description || ''}
                    onChange={handleChange}
                    placeholder="Brief overview of course targets and key skills..."
                  />
                </div>
              </div>
            )}

            {/* Tab 2: Curriculum & Moduls */}
            {activeTab === 'curriculum' && (
              <div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="lacture">
                      Total Lectures
                    </label>
                    <input
                      id="lacture"
                      name="lacture"
                      type="text"
                      className="innova-form-control"
                      value={formData.lacture || ''}
                      onChange={handleChange}
                      placeholder="e.g. 36"
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="totalmoduls">
                      Total Modules
                    </label>
                    <input
                      id="totalmoduls"
                      name="totalmoduls"
                      type="text"
                      className="innova-form-control"
                      value={formData.totalmoduls || ''}
                      onChange={handleChange}
                      placeholder="e.g. 8"
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="totalproject">
                      Projects Included
                    </label>
                    <input
                      id="totalproject"
                      name="totalproject"
                      type="text"
                      className="innova-form-control"
                      value={formData.totalproject || ''}
                      onChange={handleChange}
                      placeholder="e.g. 4 Practical Projects"
                    />
                  </div>
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="software">
                    Software & Tools Required
                  </label>
                  <input
                    id="software"
                    name="software"
                    type="text"
                    className="innova-form-control"
                    value={formData.software || ''}
                    onChange={handleChange}
                    placeholder="e.g. VS Code, Git, Docker, Postman, Figma"
                  />
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="overview">
                    Detailed Course Overview
                  </label>
                  <textarea
                    id="overview"
                    name="overview"
                    rows={4}
                    className="innova-form-control"
                    value={formData.overview || ''}
                    onChange={handleChange}
                    placeholder="In-depth explanation of core subject matters covered..."
                  />
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="curriculum">
                    Syllabus / Curriculum Outline
                  </label>
                  <textarea
                    id="curriculum"
                    name="curriculum"
                    rows={4}
                    className="innova-form-control"
                    value={formData.curriculum || ''}
                    onChange={handleChange}
                    placeholder="Week-by-week or module-by-module breakdown..."
                  />
                </div>
              </div>
            )}

            {/* Tab 3: Career & Media */}
            {activeTab === 'career' && (
              <div>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="jobPosition">
                      Target Career Positions
                    </label>
                    <input
                      id="jobPosition"
                      name="jobPosition"
                      type="text"
                      className="innova-form-control"
                      value={formData.jobPosition || ''}
                      onChange={handleChange}
                      placeholder="e.g. Frontend Engineer, React Developer, Full Stack Intern"
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="trainee">
                      Target Audience / Trainees
                    </label>
                    <input
                      id="trainee"
                      name="trainee"
                      type="text"
                      className="innova-form-control"
                      value={formData.trainee || ''}
                      onChange={handleChange}
                      placeholder="e.g. CSE Undergraduates, Career Changers, Junior Developers"
                    />
                  </div>
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="jobPlacement">
                    Job Placement Support Notes
                  </label>
                  <textarea
                    id="jobPlacement"
                    name="jobPlacement"
                    rows={3}
                    className="innova-form-control"
                    value={formData.jobPlacement || ''}
                    onChange={handleChange}
                    placeholder="Details about internship partnerships, resume reviews, mock interviews..."
                  />
                </div>

                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: '1rem' }}>
                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="video">
                      Preview Video URL
                    </label>
                    <input
                      id="video"
                      name="video"
                      type="text"
                      className="innova-form-control"
                      value={formData.video || ''}
                      onChange={handleChange}
                      placeholder="e.g. https://www.youtube.com/watch?v=..."
                    />
                  </div>

                  <div className="innova-form-group">
                    <label className="innova-form-label" htmlFor="image">
                      Banner Image Path
                    </label>
                    <input
                      id="image"
                      name="image"
                      type="text"
                      className="innova-form-control"
                      value={formData.image || ''}
                      onChange={handleChange}
                      placeholder="e.g. courses/web-development.jpg"
                    />
                  </div>
                </div>
              </div>
            )}

            {/* Tab 4: Status & Settings */}
            {activeTab === 'settings' && (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem' }}>
                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="IsActive">
                    Status
                  </label>
                  <select
                    id="IsActive"
                    name="IsActive"
                    className="innova-form-control"
                    value={formData.IsActive ?? 1}
                    onChange={handleChange}
                  >
                    <option value={1}>Active</option>
                    <option value={0}>Deactivated</option>
                  </select>
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="featured">
                    Featured on Homepage
                  </label>
                  <select
                    id="featured"
                    name="featured"
                    className="innova-form-control"
                    value={formData.featured ?? 0}
                    onChange={handleChange}
                  >
                    <option value={0}>Standard (No)</option>
                    <option value={1}>Featured (Yes)</option>
                  </select>
                </div>

                <div className="innova-form-group">
                  <label className="innova-form-label" htmlFor="IsCourse">
                    Catalog Display
                  </label>
                  <select
                    id="IsCourse"
                    name="IsCourse"
                    className="innova-form-control"
                    value={formData.IsCourse ?? 1}
                    onChange={handleChange}
                  >
                    <option value={1}>Show in Public Catalog</option>
                    <option value={0}>Hidden / Internal Only</option>
                  </select>
                </div>
              </div>
            )}
          </div>

          {/* Footer Controls */}
          <div className="innova-modal-footer">
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={onClose}
              disabled={submitting}
            >
              Cancel
            </button>
            <button
              type="submit"
              className="innova-btn innova-btn-primary"
              disabled={submitting}
            >
              {submitting ? (
                <LoadingSpinner size="sm" label="Saving..." />
              ) : isEditing ? (
                'Update Course'
              ) : (
                'Create Course'
              )}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

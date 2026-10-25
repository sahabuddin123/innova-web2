import React, { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Admission,
  AdmissionFilters,
  Course,
  ApiMeta,
  ADMISSION_STATUS_MAP,
  COURSE_TYPE_MAP,
} from '../../../types';
import { admissionService, courseService } from '../../../services';
import {
  AdmissionDetailModal,
  AdmissionStatusModal,
  AdmissionFormModal,
  PublicAdmissionForm,
} from '../../../components/admissions';
import { Card, Alert, LoadingSpinner } from '../../../components/common';
import { useAuth } from '../../../hooks/useAuth';

type AdmissionStatusTab = 'all' | '0' | '1' | '2' | '3' | '4' | '5' | '6';

export const AdmissionListPage: React.FC = () => {
  const { hasPermission } = useAuth();
  const navigate = useNavigate();

  // RBAC Permission checks
  const canList = hasPermission('student-list');
  const canEdit = hasPermission('student-edit');
  const canCreate = hasPermission('student-create') || canEdit;

  // Main state
  const [admissions, setAdmissions] = useState<Admission[]>([]);
  const [meta, setMeta] = useState<ApiMeta | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Filters state
  const [statusTab, setStatusTab] = useState<AdmissionStatusTab>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCourse, setSelectedCourse] = useState<string>('');
  const [selectedCourseType, setSelectedCourseType] = useState<string>('all');
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Courses catalog for filtering
  const [courses, setCourses] = useState<Course[]>([]);

  // Modals state
  const [detailAdmission, setDetailAdmission] = useState<Admission | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState<boolean>(false);

  const [formAdmission, setFormAdmission] = useState<Admission | null>(null);
  const [isFormOpen, setIsFormOpen] = useState<boolean>(false);

  const [statusAdmission, setStatusAdmission] = useState<Admission | null>(null);
  const [isStatusOpen, setIsStatusOpen] = useState<boolean>(false);

  const [isPreviewOpen, setIsPreviewOpen] = useState<boolean>(false);

  // Enrollment warning / modal
  const [enrollWarning, setEnrollWarning] = useState<{
    admission: Admission;
    alreadyEnrolled: boolean;
  } | null>(null);

  // Notification banner
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Fetch courses catalog on mount
  useEffect(() => {
    courseService
      .getCourses({ per_page: 100 })
      .then((res) => {
        setCourses(res.data || []);
      })
      .catch(() => {
        // Fallback gracefully
      });
  }, []);

  // Fetch admissions
  const fetchAdmissions = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const filters: AdmissionFilters = {
        page: currentPage,
        per_page: 15,
      };

      if (searchQuery.trim()) {
        filters.search = searchQuery.trim();
      }

      if (selectedCourse) {
        filters.courseId = Number(selectedCourse);
      }

      if (selectedCourseType !== 'all') {
        filters.courseType = Number(selectedCourseType);
      }

      if (statusTab !== 'all') {
        filters.isActive = Number(statusTab);
      }

      const res = await admissionService.getAdmissions(filters);
      setAdmissions(res.data || []);
      setMeta(res.meta || null);
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setError(
        apiErr.response?.data?.message ||
          apiErr.message ||
          'Failed to load online admissions. Please check your network connection.'
      );
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, searchQuery, selectedCourse, selectedCourseType, statusTab]);

  useEffect(() => {
    fetchAdmissions();
  }, [fetchAdmissions]);

  // Handle enrollment check & flow
  const handleEnrollClick = async (adm: Admission) => {
    setIsLoading(true);
    try {
      const alreadyExists = await admissionService.checkExistingStudent(adm.phone);
      setEnrollWarning({
        admission: adm,
        alreadyEnrolled: alreadyExists,
      });
    } catch {
      setEnrollWarning({
        admission: adm,
        alreadyEnrolled: false,
      });
    } finally {
      setIsLoading(false);
    }
  };

  const handleConfirmEnrollment = async (adm: Admission) => {
    try {
      // Transition admission status to "Admission Complete" (code 3)
      await admissionService.updateStatus(adm.id, 3);
      setSuccessMessage(
        `Admission #${adm.id} (${adm.username}) marked as Admission Complete! Navigating to Student Directory...`
      );
      setEnrollWarning(null);
      setIsDetailOpen(false);
      fetchAdmissions();

      // Navigate to students list with prefill query
      setTimeout(() => {
        navigate(`/students?search=${encodeURIComponent(adm.phone)}`);
      }, 1200);
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setError(apiErr.response?.data?.message || 'Failed to update admission status during enrollment.');
    }
  };

  const handleResetFilters = () => {
    setStatusTab('all');
    setSearchQuery('');
    setSelectedCourse('');
    setSelectedCourseType('all');
    setCurrentPage(1);
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
      {/* Page Title & Action Bar */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: '1rem',
        }}
      >
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-900)' }}>
            Online Student Admissions
          </h1>
          <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
            Review, follow up, and manage incoming online admission inquiries and prospective student leads
          </p>
        </div>

        <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
          <button
            type="button"
            className="innova-btn innova-btn-outline"
            onClick={() => setIsPreviewOpen(true)}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ marginRight: '0.35rem' }}>
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            Public Form Preview
          </button>

          {canCreate && (
            <button
              type="button"
              className="innova-btn innova-btn-primary"
              onClick={() => {
                setFormAdmission(null);
                setIsFormOpen(true);
              }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ marginRight: '0.35rem' }}>
                <line x1="12" y1="5" x2="12" y2="19" />
                <line x1="5" y1="12" x2="19" y2="12" />
              </svg>
              New Inquiry
            </button>
          )}
        </div>
      </div>

      {/* Alerts */}
      {successMessage && (
        <Alert type="success" title="Success">
          {successMessage}
        </Alert>
      )}

      {error && (
        <Alert type="error" title="Notice">
          {error}
        </Alert>
      )}

      {/* Lifecycle Status Tabs */}
      <div style={{ display: 'flex', borderBottom: '2px solid var(--innova-gray-200)', gap: '0.25rem', overflowX: 'auto', paddingBottom: '2px' }}>
        <button
          type="button"
          className={`innova-tab ${statusTab === 'all' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('all');
            setCurrentPage(1);
          }}
        >
          All Inquiries
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '0' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('0');
            setCurrentPage(1);
          }}
        >
          Waiting for Contact (0)
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '1' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('1');
            setCurrentPage(1);
          }}
        >
          Call Not Received (1)
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '2' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('2');
            setCurrentPage(1);
          }}
        >
          Waiting For Admission (2)
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '3' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('3');
            setCurrentPage(1);
          }}
        >
          Admission Complete (3)
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '4' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('4');
            setCurrentPage(1);
          }}
        >
          SMS Sent (4)
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '5' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('5');
            setCurrentPage(1);
          }}
        >
          Not Interested (5)
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '6' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('6');
            setCurrentPage(1);
          }}
        >
          Restricted (6)
        </button>
      </div>

      {/* Filter Bar Card */}
      <Card>
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
            gap: '1rem',
            alignItems: 'flex-end',
          }}
        >
          {/* Search Query */}
          <div className="innova-form-group" style={{ margin: 0 }}>
            <label htmlFor="search_query" className="innova-form-label">
              Search Inquiries
            </label>
            <input
              type="text"
              id="search_query"
              className="innova-form-input"
              placeholder="Name, phone, or email..."
              value={searchQuery}
              onChange={(e) => {
                setSearchQuery(e.target.value);
                setCurrentPage(1);
              }}
            />
          </div>

          {/* Course Filter */}
          <div className="innova-form-group" style={{ margin: 0 }}>
            <label htmlFor="filter_course" className="innova-form-label">
              Filter by Course
            </label>
            <select
              id="filter_course"
              className="innova-form-input"
              value={selectedCourse}
              onChange={(e) => {
                setSelectedCourse(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Courses</option>
              {courses.map((course) => (
                <option key={course.id} value={course.id}>
                  {course.name} {course.shortTitle ? `(${course.shortTitle})` : ''}
                </option>
              ))}
            </select>
          </div>

          {/* Delivery Format Filter */}
          <div className="innova-form-group" style={{ margin: 0 }}>
            <label htmlFor="filter_coursetype" className="innova-form-label">
              Class Delivery Format
            </label>
            <select
              id="filter_coursetype"
              className="innova-form-input"
              value={selectedCourseType}
              onChange={(e) => {
                setSelectedCourseType(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="all">All Formats</option>
              <option value="0">Offline (In-Person)</option>
              <option value="1">Online (Virtual Live)</option>
            </select>
          </div>

          {/* Reset Filters */}
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              className="innova-btn innova-btn-secondary"
              onClick={handleResetFilters}
              style={{ width: '100%' }}
            >
              Reset Filters
            </button>
          </div>
        </div>
      </Card>

      {/* Directory Table Card */}
      <Card>
        {isLoading ? (
          <div style={{ padding: '3rem 0', textAlign: 'center' }}>
            <LoadingSpinner size="lg" label="Loading admission inquiries..." />
          </div>
        ) : admissions.length === 0 ? (
          <div style={{ padding: '3rem 0', textAlign: 'center' }}>
            <div
              style={{
                width: '56px',
                height: '56px',
                borderRadius: '50%',
                backgroundColor: 'var(--innova-gray-100)',
                color: 'var(--innova-gray-400)',
                display: 'inline-flex',
                alignItems: 'center',
                justifyContent: 'center',
                marginBottom: '1rem',
              }}
            >
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
                <line x1="16" y1="13" x2="8" y2="13" />
                <line x1="16" y1="17" x2="8" y2="17" />
              </svg>
            </div>
            <h3 style={{ margin: '0 0 0.5rem 0', color: 'var(--innova-gray-800)', fontSize: '1.1rem' }}>
              No Admission Inquiries Found
            </h3>
            <p style={{ margin: '0 0 1.25rem 0', color: 'var(--innova-gray-500)', fontSize: '0.9rem' }}>
              {searchQuery || selectedCourse || statusTab !== 'all' || selectedCourseType !== 'all'
                ? 'No inquiries match the current search filters. Try clearing your filters.'
                : 'No online student admissions have been recorded yet.'}
            </p>
            {(searchQuery || selectedCourse || statusTab !== 'all' || selectedCourseType !== 'all') && (
              <button
                type="button"
                className="innova-btn innova-btn-outline"
                onClick={handleResetFilters}
              >
                Clear Search Filters
              </button>
            )}
          </div>
        ) : (
          <div style={{ overflowX: 'auto' }}>
            <table className="innova-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead>
                <tr>
                  <th style={{ width: '50px' }}>#</th>
                  <th>Student Name</th>
                  <th>Contact Info</th>
                  <th>Course & Fee</th>
                  <th>Format</th>
                  <th>Lifecycle Status</th>
                  <th>Inquiry Date</th>
                  <th style={{ width: '140px', textAlign: 'center' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {admissions.map((adm, index) => {
                  const statusMeta = ADMISSION_STATUS_MAP[adm.isActive] || {
                    label: `Status ${adm.isActive}`,
                    badgeClass: 'innova-badge-gray',
                  };
                  const courseTypeMeta = COURSE_TYPE_MAP[adm.courseType] || {
                    label: adm.courseType === 1 ? 'Online' : 'Offline',
                    badgeClass: 'innova-badge-gray',
                  };

                  const rowIndex =
                    meta && typeof meta.current_page === 'number' && typeof meta.per_page === 'number'
                      ? (meta.current_page - 1) * meta.per_page + (index + 1)
                      : index + 1;

                  const initials = adm.username
                    .split(' ')
                    .filter(Boolean)
                    .map((n) => n[0])
                    .join('')
                    .substring(0, 2)
                    .toUpperCase();

                  const createdDate = adm.created_at
                    ? new Date(adm.created_at).toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                      })
                    : '—';

                  return (
                    <tr key={adm.id}>
                      <td style={{ color: 'var(--innova-gray-500)', fontSize: '0.85rem' }}>
                        {rowIndex}
                      </td>

                      {/* Student Name */}
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
                          <div
                            style={{
                              width: '36px',
                              height: '36px',
                              borderRadius: '50%',
                              backgroundColor: 'var(--innova-primary-100)',
                              color: 'var(--innova-primary-700)',
                              display: 'flex',
                              alignItems: 'center',
                              justifyContent: 'center',
                              fontSize: '0.85rem',
                              fontWeight: 700,
                              flexShrink: 0,
                            }}
                          >
                            {initials || 'ST'}
                          </div>
                          <div>
                            <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                              {adm.username}
                            </div>
                            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                              Ref: #ADM-{adm.id}
                            </div>
                          </div>
                        </div>
                      </td>

                      {/* Contact Info */}
                      <td>
                        <div style={{ fontWeight: 500 }}>
                          <a
                            href={`tel:${adm.phone}`}
                            style={{ color: 'var(--innova-primary-600)', textDecoration: 'none' }}
                          >
                            {adm.phone}
                          </a>
                        </div>
                        {adm.email && (
                          <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                            <a
                              href={`mailto:${adm.email}`}
                              style={{ color: 'var(--innova-gray-600)', textDecoration: 'none' }}
                            >
                              {adm.email}
                            </a>
                          </div>
                        )}
                      </td>

                      {/* Course */}
                      <td>
                        <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)', fontSize: '0.9rem' }}>
                          {adm.course?.name || `Course #${adm.courseId}`}
                          {adm.course?.shortTitle && (
                            <span style={{ marginLeft: '0.35rem', fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                              ({adm.course.shortTitle})
                            </span>
                          )}
                        </div>
                        {adm.course?.coursefee && (
                          <div style={{ fontSize: '0.75rem', color: 'var(--innova-primary-700)', fontWeight: 600 }}>
                            ৳ {Number(adm.course.coursefee).toLocaleString()}
                          </div>
                        )}
                      </td>

                      {/* Format */}
                      <td>
                        <span className={`innova-badge ${courseTypeMeta.badgeClass}`}>
                          {courseTypeMeta.label}
                        </span>
                      </td>

                      {/* Status */}
                      <td>
                        <span className={`innova-badge ${statusMeta.badgeClass}`}>
                          {statusMeta.label}
                        </span>
                      </td>

                      {/* Date */}
                      <td style={{ fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
                        {createdDate}
                      </td>

                      {/* Actions */}
                      <td style={{ textAlign: 'center' }}>
                        <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                          {/* View Details */}
                          {canList && (
                            <button
                              type="button"
                              className="innova-btn-icon"
                              onClick={() => {
                                setDetailAdmission(adm);
                                setIsDetailOpen(true);
                              }}
                              title="View Details"
                              aria-label={`View inquiry ${adm.username}`}
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                              </svg>
                            </button>
                          )}

                          {/* Quick Status Update */}
                          {canEdit && (
                            <button
                              type="button"
                              className="innova-btn-icon"
                              onClick={() => {
                                setStatusAdmission(adm);
                                setIsStatusOpen(true);
                              }}
                              title="Update Status"
                              aria-label={`Update status for ${adm.username}`}
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M23 4v6h-6" />
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                              </svg>
                            </button>
                          )}

                          {/* Edit Inquiry */}
                          {canEdit && (
                            <button
                              type="button"
                              className="innova-btn-icon"
                              onClick={() => {
                                setFormAdmission(adm);
                                setIsFormOpen(true);
                              }}
                              title="Edit Inquiry"
                              aria-label={`Edit inquiry ${adm.username}`}
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                              </svg>
                            </button>
                          )}

                          {/* Enroll as Student */}
                          {canCreate && (
                            <button
                              type="button"
                              className="innova-btn-icon"
                              style={{ color: 'var(--innova-primary-600)' }}
                              onClick={() => handleEnrollClick(adm)}
                              title="Enroll as Student"
                              aria-label={`Enroll ${adm.username} as student`}
                            >
                              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="8.5" cy="7" r="4" />
                                <line x1="20" y1="8" x2="20" y2="14" />
                                <line x1="23" y1="11" x2="17" y2="11" />
                              </svg>
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination Bar */}
        {meta && typeof meta.last_page === 'number' && meta.last_page > 1 && (
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              marginTop: '1.25rem',
              paddingTop: '1rem',
              borderTop: '1px solid var(--innova-gray-200)',
              flexWrap: 'wrap',
              gap: '0.75rem',
            }}
          >
            <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
              Showing {admissions.length} of {meta.total ?? admissions.length} inquiries (Page {meta.current_page ?? 1} of {meta.last_page})
            </div>

            <div style={{ display: 'flex', gap: '0.35rem' }}>
              <button
                type="button"
                className="innova-btn innova-btn-sm innova-btn-outline"
                disabled={currentPage <= 1 || isLoading}
                onClick={() => setCurrentPage((prev) => Math.max(1, prev - 1))}
              >
                &larr; Previous
              </button>

              <button
                type="button"
                className="innova-btn innova-btn-sm innova-btn-outline"
                disabled={currentPage >= (meta.last_page || 1) || isLoading}
                onClick={() => setCurrentPage((prev) => Math.min(meta.last_page || 1, prev + 1))}
              >
                Next &rarr;
              </button>
            </div>
          </div>
        )}
      </Card>

      {/* Detail Modal */}
      <AdmissionDetailModal
        admission={detailAdmission}
        isOpen={isDetailOpen}
        onClose={() => {
          setIsDetailOpen(false);
          setDetailAdmission(null);
        }}
        canEdit={canEdit}
        onEdit={(adm) => {
          setIsDetailOpen(false);
          setFormAdmission(adm);
          setIsFormOpen(true);
        }}
        onChangeStatus={(adm) => {
          setIsDetailOpen(false);
          setStatusAdmission(adm);
          setIsStatusOpen(true);
        }}
        onEnroll={(adm) => handleEnrollClick(adm)}
      />

      {/* Status Modal */}
      <AdmissionStatusModal
        admission={statusAdmission}
        isOpen={isStatusOpen}
        onClose={() => {
          setIsStatusOpen(false);
          setStatusAdmission(null);
        }}
        onSuccess={(updated) => {
          setSuccessMessage(`Updated status for ${updated.username} successfully!`);
          fetchAdmissions();
        }}
      />

      {/* Form Modal (Create / Edit) */}
      <AdmissionFormModal
        admission={formAdmission}
        isOpen={isFormOpen}
        onClose={() => {
          setIsFormOpen(false);
          setFormAdmission(null);
        }}
        onSuccess={(saved) => {
          setSuccessMessage(
            formAdmission
              ? `Admission #${saved.id} updated successfully!`
              : `New admission inquiry #${saved.id} for ${saved.username} created successfully!`
          );
          fetchAdmissions();
        }}
      />

      {/* Public Form Preview Modal */}
      {isPreviewOpen && (
        <div className="innova-modal-backdrop" onClick={() => setIsPreviewOpen(false)} role="dialog" aria-modal="true">
          <div
            className="innova-modal"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '720px' }}
          >
            <div className="innova-modal-header">
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                  Public Frontend Interface Preview
                </div>
                <h2 className="innova-modal-title">Online Student Admission Application Form</h2>
              </div>
              <button
                type="button"
                className="innova-modal-close"
                onClick={() => setIsPreviewOpen(false)}
                aria-label="Close modal"
              >
                &times;
              </button>
            </div>
            <div className="innova-modal-body">
              <PublicAdmissionForm
                onSubmitted={() => {
                  fetchAdmissions();
                }}
              />
            </div>
          </div>
        </div>
      )}

      {/* Enrollment Safeguard Modal */}
      {enrollWarning && (
        <div className="innova-modal-backdrop" onClick={() => setEnrollWarning(null)} role="dialog" aria-modal="true">
          <div
            className="innova-modal"
            onClick={(e) => e.stopPropagation()}
            style={{ maxWidth: '520px' }}
          >
            <div className="innova-modal-header">
              <div>
                <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                  Enrollment Workflow
                </div>
                <h2 className="innova-modal-title">
                  {enrollWarning.alreadyEnrolled
                    ? 'Student Already Enrolled'
                    : `Enroll ${enrollWarning.admission.username}?`}
                </h2>
              </div>
              <button
                type="button"
                className="innova-modal-close"
                onClick={() => setEnrollWarning(null)}
                aria-label="Close"
              >
                &times;
              </button>
            </div>

            <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
              {enrollWarning.alreadyEnrolled ? (
                <Alert type="warning" title="Duplicate Record Safeguard">
                  A registered student with phone number{' '}
                  <strong>{enrollWarning.admission.phone}</strong> is already present in the Student Directory!
                  To prevent duplicate student accounts and ensure audit integrity, this inquiry should be linked to the existing student or closed without generating a duplicate profile.
                </Alert>
              ) : (
                <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-700)' }}>
                  Are you ready to finalize admission for{' '}
                  <strong>{enrollWarning.admission.username}</strong>? This will update the inquiry status to{' '}
                  <strong>Admission Complete (Status 3)</strong> and allow you to configure batch enrollment and tuition fees in the Student Directory.
                </p>
              )}
            </div>

            <div className="innova-modal-footer">
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={() => setEnrollWarning(null)}
              >
                Close
              </button>
              {enrollWarning.alreadyEnrolled ? (
                <button
                  type="button"
                  className="innova-btn innova-btn-primary"
                  onClick={() => {
                    setEnrollWarning(null);
                    navigate(`/students?search=${encodeURIComponent(enrollWarning.admission.phone)}`);
                  }}
                >
                  View Existing Student Profile
                </button>
              ) : (
                <button
                  type="button"
                  className="innova-btn innova-btn-primary"
                  onClick={() => handleConfirmEnrollment(enrollWarning.admission)}
                >
                  Finalize & Enroll Student
                </button>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

import React, { useState, useEffect, useCallback } from 'react';
import { Course, CourseCategory, Teacher, CourseFilters, ApiMeta } from '../../../types';
import { courseService } from '../../../services';
import { useAuth } from '../../../hooks/useAuth';
import { LoadingSpinner, Alert } from '../../../components/common';
import { CourseFormModal, CourseDetailModal } from '../../../components/courses';
import { ApiErrorResponse } from '../../../types/api';

export const CourseListPage: React.FC = () => {
  const { user, hasPermission } = useAuth();
  const canCreate = hasPermission('course-create') || !!user?.is_admin;
  const canEdit = hasPermission('course-edit') || !!user?.is_admin;
  const canDelete = hasPermission('course-delete') || !!user?.is_admin;

  // Data states
  const [courses, setCourses] = useState<Course[]>([]);
  const [categories, setCategories] = useState<CourseCategory[]>([]);
  const [teachers, setTeachers] = useState<Teacher[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [meta, setMeta] = useState<ApiMeta | null>(null);

  // Filters
  const [searchTerm, setSearchTerm] = useState<string>('');
  const [selectedCategory, setSelectedCategory] = useState<string>('');
  const [selectedStatus, setSelectedStatus] = useState<string>('');
  const [selectedFeatured, setSelectedFeatured] = useState<string>('');
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Modals & Selection
  const [isFormOpen, setIsFormOpen] = useState<boolean>(false);
  const [editingCourse, setEditingCourse] = useState<Course | null>(null);
  const [viewingCourse, setViewingCourse] = useState<Course | null>(null);
  const [courseToDelete, setCourseToDelete] = useState<Course | null>(null);
  const [isDeleting, setIsDeleting] = useState<boolean>(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Load initial dropdown categories & teachers
  useEffect(() => {
    courseService.getCategories().then(setCategories).catch(() => {});
    courseService.getTeachers().then(setTeachers).catch(() => {});
  }, []);

  // Fetch courses with current filters
  const fetchCourses = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const filters: CourseFilters = {
        page: currentPage,
        per_page: 15,
      };
      if (searchTerm.trim()) filters.search = searchTerm.trim();
      if (selectedCategory) filters.category_id = Number(selectedCategory);
      if (selectedStatus !== '') filters.is_active = Number(selectedStatus);
      if (selectedFeatured !== '') filters.featured = Number(selectedFeatured);

      const result = await courseService.getCourses(filters);
      setCourses(result.data);
      setMeta(result.meta || null);
    } catch (err) {
      const apiErr = err as ApiErrorResponse;
      setError(apiErr.message || 'Failed to load courses.');
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm, selectedCategory, selectedStatus, selectedFeatured]);

  useEffect(() => {
    fetchCourses();
  }, [fetchCourses]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
    fetchCourses();
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedCategory('');
    setSelectedStatus('');
    setSelectedFeatured('');
    setCurrentPage(1);
  };

  const handleCreateNew = () => {
    setEditingCourse(null);
    setIsFormOpen(true);
  };

  const handleEdit = (course: Course) => {
    setEditingCourse(course);
    setIsFormOpen(true);
  };

  const handleView = (course: Course) => {
    setViewingCourse(course);
  };

  const handleFormSuccess = (saved: Course) => {
    setSuccessMessage(`Course "${saved.name}" successfully ${editingCourse ? 'updated' : 'created'}.`);
    setTimeout(() => setSuccessMessage(null), 5000);
    fetchCourses();
  };

  const handleDeleteConfirm = async () => {
    if (!courseToDelete) return;
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await courseService.deleteCourse(courseToDelete.id);
      setSuccessMessage(`Course "${courseToDelete.name}" successfully deleted.`);
      setTimeout(() => setSuccessMessage(null), 5000);
      setCourseToDelete(null);
      fetchCourses();
    } catch (err) {
      const apiErr = err as ApiErrorResponse;
      setDeleteError(apiErr.message || 'Failed to delete course.');
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <div>
      {/* Page Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <h1 style={{ fontSize: '1.75rem', fontWeight: 700, margin: 0, color: 'var(--innova-gray-900)' }}>
              Courses & Academic Catalogs
            </h1>
            {meta?.total !== undefined && (
              <span className="innova-badge innova-badge-primary">
                {meta.total} Total
              </span>
            )}
          </div>
          <p style={{ margin: '0.25rem 0 0 0', fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
            Manage educational programs, curriculum modules, duration, and tuition fee schedules.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="innova-btn innova-btn-primary"
            onClick={handleCreateNew}
          >
            + Create New Course
          </button>
        )}
      </div>

      {/* Success Notification */}
      {successMessage && (
        <Alert type="success" title="Success">
          {successMessage}
        </Alert>
      )}

      {/* Filter Toolbar */}
      <div
        className="innova-card"
        style={{ padding: '1rem', marginBottom: '1.5rem', backgroundColor: 'var(--innova-card-bg)' }}
      >
        <form onSubmit={handleSearchSubmit} style={{ display: 'flex', flexWrap: 'wrap', gap: '0.75rem', alignItems: 'flex-end' }}>
          {/* Search Box */}
          <div style={{ flex: '1 1 200px' }}>
            <label className="innova-form-label" htmlFor="course-search">
              Search Courses
            </label>
            <input
              id="course-search"
              type="text"
              className="innova-form-control"
              placeholder="Search by name or code..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>

          {/* Category Filter */}
          <div style={{ flex: '1 1 180px' }}>
            <label className="innova-form-label" htmlFor="category-filter">
              Category
            </label>
            <select
              id="category-filter"
              className="innova-form-control"
              value={selectedCategory}
              onChange={(e) => {
                setSelectedCategory(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Categories</option>
              {categories.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.CourseCategoris}
                </option>
              ))}
            </select>
          </div>

          {/* Status Filter */}
          <div style={{ flex: '1 1 130px' }}>
            <label className="innova-form-label" htmlFor="status-filter">
              Status
            </label>
            <select
              id="status-filter"
              className="innova-form-control"
              value={selectedStatus}
              onChange={(e) => {
                setSelectedStatus(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Statuses</option>
              <option value="1">Active</option>
              <option value="0">Deactivated</option>
            </select>
          </div>

          {/* Featured Filter */}
          <div style={{ flex: '1 1 130px' }}>
            <label className="innova-form-label" htmlFor="featured-filter">
              Featured
            </label>
            <select
              id="featured-filter"
              className="innova-form-control"
              value={selectedFeatured}
              onChange={(e) => {
                setSelectedFeatured(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All</option>
              <option value="1">Featured</option>
              <option value="0">Standard</option>
            </select>
          </div>

          {/* Filter Action Buttons */}
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button type="submit" className="innova-btn innova-btn-secondary">
              Search
            </button>
            {(searchTerm || selectedCategory || selectedStatus !== '' || selectedFeatured !== '') && (
              <button
                type="button"
                className="innova-btn innova-btn-outline"
                onClick={handleResetFilters}
              >
                Reset
              </button>
            )}
          </div>
        </form>
      </div>

      {/* Main Table Content */}
      {loading ? (
        <div style={{ padding: '3rem', display: 'flex', justifyContent: 'center' }}>
          <LoadingSpinner size="lg" label="Loading Courses Catalog..." />
        </div>
      ) : error ? (
        <Alert type="error" title="Could Not Load Courses">
          {error}
          <div style={{ marginTop: '0.5rem' }}>
            <button
              type="button"
              className="innova-btn innova-btn-sm innova-btn-outline"
              onClick={fetchCourses}
            >
              Retry
            </button>
          </div>
        </Alert>
      ) : courses.length === 0 ? (
        <div
          className="innova-card"
          style={{ padding: '3rem 1.5rem', textAlign: 'center', backgroundColor: 'var(--innova-card-bg)' }}
        >
          <div style={{ fontSize: '2.5rem', marginBottom: '0.5rem' }}>📚</div>
          <h3 style={{ margin: '0 0 0.5rem 0', color: 'var(--innova-gray-800)' }}>No Courses Found</h3>
          <p style={{ margin: '0 0 1.25rem 0', color: 'var(--innova-gray-500)', fontSize: '0.9rem' }}>
            No course records matched the selected filter criteria.
          </p>
          {canCreate && (
            <button type="button" className="innova-btn innova-btn-primary" onClick={handleCreateNew}>
              Create First Course
            </button>
          )}
        </div>
      ) : (
        <div className="innova-table-container">
          <table className="innova-table">
            <thead>
              <tr>
                <th style={{ width: '48px' }}>#</th>
                <th>Course Name</th>
                <th>Category</th>
                <th>Duration</th>
                <th>Modules</th>
                <th>Tuition Fee</th>
                <th>Status</th>
                <th>Featured</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {courses.map((course, idx) => {
                const rowNum = meta ? ((meta.current_page || 1) - 1) * (meta.per_page || 15) + idx + 1 : idx + 1;
                return (
                  <tr key={course.id}>
                    <td style={{ color: 'var(--innova-gray-500)', fontWeight: 500 }}>
                      {rowNum}
                    </td>
                    <td>
                      <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                        {course.name}
                      </div>
                      {course.shortTitle && (
                        <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                          Code: <code>{course.shortTitle}</code>
                        </span>
                      )}
                    </td>
                    <td>
                      {(course.course_category || course.CourseCategory)?.CourseCategoris ? (
                        <span className="innova-badge innova-badge-gray">
                          {(course.course_category || course.CourseCategory)!.CourseCategoris}
                        </span>
                      ) : (
                        <span style={{ color: 'var(--innova-gray-400)', fontSize: '0.8rem' }}>None</span>
                      )}
                    </td>
                    <td>
                      {course.courseduration ? `${course.courseduration} Mos` : '—'}
                    </td>
                    <td>
                      {course.totalmoduls ? `${course.totalmoduls} Mod` : '—'}
                    </td>
                    <td>
                      <span style={{ fontWeight: 600, color: 'var(--innova-primary)' }}>
                        {course.coursefee} BDT
                      </span>
                      {course.Discount ? (
                        <div style={{ fontSize: '0.7rem', color: 'var(--innova-success)' }}>
                          -{course.Discount} Discount
                        </div>
                      ) : null}
                    </td>
                    <td>
                      <span className={`innova-badge ${course.IsActive === 1 ? 'innova-badge-success' : 'innova-badge-error'}`}>
                        {course.IsActive === 1 ? 'Active' : 'Deactivated'}
                      </span>
                    </td>
                    <td>
                      {course.featured === 1 ? (
                        <span className="innova-badge innova-badge-primary">Yes</span>
                      ) : (
                        <span style={{ color: 'var(--innova-gray-400)', fontSize: '0.8rem' }}>No</span>
                      )}
                    </td>
                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                      <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                        <button
                          type="button"
                          className="innova-btn innova-btn-sm innova-btn-secondary"
                          onClick={() => handleView(course)}
                          title="View Course Details"
                        >
                          View
                        </button>

                        {canEdit && (
                          <button
                            type="button"
                            className="innova-btn innova-btn-sm innova-btn-outline"
                            onClick={() => handleEdit(course)}
                            title="Edit Course"
                          >
                            Edit
                          </button>
                        )}

                        {canDelete && (
                          <button
                            type="button"
                            className="innova-btn innova-btn-sm innova-btn-danger"
                            onClick={() => {
                              setDeleteError(null);
                              setCourseToDelete(course);
                            }}
                            title="Delete Course"
                          >
                            Delete
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
      {meta && (meta.last_page || 1) > 1 && (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '1.25rem', flexWrap: 'wrap', gap: '0.5rem' }}>
          <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
            Showing page {meta.current_page} of {meta.last_page} ({meta.total} courses total)
          </div>

          <div style={{ display: 'flex', gap: '0.35rem' }}>
            <button
              type="button"
              className="innova-btn innova-btn-sm innova-btn-secondary"
              disabled={meta.current_page === 1}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
            >
              &larr; Previous
            </button>
            <button
              type="button"
              className="innova-btn innova-btn-sm innova-btn-secondary"
              disabled={meta.current_page === meta.last_page}
              onClick={() => setCurrentPage((p) => Math.min(meta.last_page || 1, p + 1))}
            >
              Next &rarr;
            </button>
          </div>
        </div>
      )}

      {/* Modals */}
      <CourseFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        onSuccess={handleFormSuccess}
        course={editingCourse}
        categories={categories}
        teachers={teachers}
      />

      <CourseDetailModal
        isOpen={!!viewingCourse}
        onClose={() => setViewingCourse(null)}
        course={viewingCourse}
        canEdit={canEdit}
        onEdit={(c) => {
          setViewingCourse(null);
          handleEdit(c);
        }}
      />

      {/* Delete Confirmation Modal */}
      {courseToDelete && (
        <div className="innova-modal-backdrop" onClick={() => !isDeleting && setCourseToDelete(null)} role="dialog" aria-modal="true">
          <div className="innova-modal" onClick={(e) => e.stopPropagation()}>
            <div className="innova-modal-header">
              <h2 className="innova-modal-title" style={{ color: 'var(--innova-error)' }}>
                Confirm Course Deletion
              </h2>
              <button
                type="button"
                className="innova-btn innova-btn-sm innova-btn-secondary"
                onClick={() => setCourseToDelete(null)}
                disabled={isDeleting}
              >
                &times;
              </button>
            </div>

            <div className="innova-modal-body">
              {deleteError ? (
                <Alert type="error" title="Cannot Delete Course">
                  {deleteError}
                </Alert>
              ) : (
                <p style={{ margin: 0, fontSize: '0.95rem', color: 'var(--innova-gray-700)' }}>
                  Are you sure you want to permanently delete course <strong>{courseToDelete.name}</strong>?
                </p>
              )}
              <p style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)', marginTop: '0.5rem' }}>
                Note: In accordance with academic integrity rules, courses with active enrolled students cannot be deleted.
              </p>
            </div>

            <div className="innova-modal-footer">
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={() => setCourseToDelete(null)}
                disabled={isDeleting}
              >
                Cancel
              </button>
              <button
                type="button"
                className="innova-btn innova-btn-danger"
                onClick={handleDeleteConfirm}
                disabled={isDeleting}
              >
                {isDeleting ? <LoadingSpinner size="sm" label="Deleting..." /> : 'Yes, Delete Course'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

import React, { useState, useEffect, useCallback, useTransition } from 'react';
import {
  Student,
  Course,
  Batch,
  ApiMeta,
  STUDENT_STATUS_MAP,
} from '../../../types';
import { studentService, courseService, batchService } from '../../../services';
import { useAuth } from '../../../hooks/useAuth';
import { Card, Alert } from '../../../components/common';
import {
  StudentDetailModal,
  StudentFormModal,
  StudentStatusModal,
} from '../../../components/students';

type StatusFilterTab = 'all' | '1' | '2' | '3' | '4' | '5';

export const StudentListPage: React.FC = () => {
  const { hasPermission } = useAuth();
  const [, startTransition] = useTransition();

  // Permissions
  const canCreate = hasPermission('student-create');
  const canEdit = hasPermission('student-edit');
  const canDelete = hasPermission('student-delete');

  // List and metadata state
  const [students, setStudents] = useState<Student[]>([]);
  const [meta, setMeta] = useState<ApiMeta | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Filters state
  const [statusTab, setStatusTab] = useState<StatusFilterTab>('all');
  const [searchQuery, setSearchQuery] = useState<string>('');
  const [selectedCourse, setSelectedCourse] = useState<string>('');
  const [selectedBatch, setSelectedBatch] = useState<string>('');
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Lookup catalogs for filters
  const [courses, setCourses] = useState<Course[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);

  // Modals state
  const [detailStudent, setDetailStudent] = useState<Student | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState<boolean>(false);

  const [formStudent, setFormStudent] = useState<Student | null>(null);
  const [isFormOpen, setIsFormOpen] = useState<boolean>(false);

  const [statusStudent, setStatusStudent] = useState<Student | null>(null);
  const [isStatusOpen, setIsStatusOpen] = useState<boolean>(false);

  const [studentToDelete, setStudentToDelete] = useState<Student | null>(null);
  const [isDeleting, setIsDeleting] = useState<boolean>(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  // Load lookup catalogs on mount
  useEffect(() => {
    let isMounted = true;
    const fetchCatalogs = async () => {
      try {
        const [coursesRes, batchesRes] = await Promise.all([
          courseService.getCourses({ per_page: 100 }),
          batchService.getBatches({ per_page: 100 }),
        ]);
        if (isMounted) {
          setCourses(coursesRes.data || []);
          setBatches(batchesRes.data || []);
        }
      } catch {
        // Dropdown loading fallback
      }
    };

    fetchCatalogs();
    return () => {
      isMounted = false;
    };
  }, []);

  // Fetch student directory
  const fetchStudents = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const filters: Record<string, unknown> = {
        page: currentPage,
        per_page: 15,
      };

      if (searchQuery.trim()) {
        filters.search = searchQuery.trim();
      }

      if (statusTab !== 'all') {
        filters.is_active = Number(statusTab);
      }

      if (selectedCourse) {
        filters.course_id = Number(selectedCourse);
      }

      if (selectedBatch) {
        filters.batch_id = Number(selectedBatch);
      }

      const res = await studentService.getStudents(filters);
      startTransition(() => {
        setStudents(res.data);
        if (res.meta) setMeta(res.meta);
      });
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setError(apiErr?.response?.data?.message || apiErr?.message || 'Failed to load student directory.');
    } finally {
      setIsLoading(false);
    }
  }, [currentPage, statusTab, searchQuery, selectedCourse, selectedBatch]);

  useEffect(() => {
    fetchStudents();
  }, [fetchStudents]);

  // Handlers
  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
    fetchStudents();
  };

  const handleResetFilters = () => {
    setStatusTab('all');
    setSearchQuery('');
    setSelectedCourse('');
    setSelectedBatch('');
    setCurrentPage(1);
  };

  const handleCreateNew = () => {
    setFormStudent(null);
    setIsFormOpen(true);
  };

  const handleEdit = (student: Student) => {
    setFormStudent(student);
    setIsFormOpen(true);
  };

  const handleView = (student: Student) => {
    setDetailStudent(student);
    setIsDetailOpen(true);
  };

  const handleStatusChange = (student: Student) => {
    setStatusStudent(student);
    setIsStatusOpen(true);
  };

  const handleDeleteConfirm = async () => {
    if (!studentToDelete) return;
    setIsDeleting(true);
    setDeleteError(null);

    try {
      await studentService.deleteStudent(studentToDelete.id);
      setStudentToDelete(null);
      fetchStudents();
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setDeleteError(apiErr?.response?.data?.message || apiErr?.message || 'Failed to delete student.');
    } finally {
      setIsDeleting(false);
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
      {/* Page Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ margin: 0, fontSize: '1.5rem', fontWeight: 800, color: 'var(--innova-gray-900)' }}>
            Student Management
          </h1>
          <p style={{ margin: '0.25rem 0 0', color: 'var(--innova-gray-500)', fontSize: '0.875rem' }}>
            Browse enrolled student profiles, track batch allocations, and manage lifecycle status.
          </p>
        </div>

        {canCreate && (
          <button type="button" className="innova-btn innova-btn-primary" onClick={handleCreateNew}>
            + Enroll Student
          </button>
        )}
      </div>

      {/* Lifecycle Status Tabs */}
      <div style={{ display: 'flex', borderBottom: '2px solid var(--innova-gray-200)', gap: '0.25rem', overflowX: 'auto' }}>
        <button
          type="button"
          className={`innova-tab ${statusTab === 'all' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('all');
            setCurrentPage(1);
          }}
        >
          All Students
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '1' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('1');
            setCurrentPage(1);
          }}
        >
          Active / Regular
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '2' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('2');
            setCurrentPage(1);
          }}
        >
          Irregular
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '3' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('3');
            setCurrentPage(1);
          }}
        >
          Complete
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '4' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('4');
            setCurrentPage(1);
          }}
        >
          Restricted
        </button>
        <button
          type="button"
          className={`innova-tab ${statusTab === '5' ? 'innova-tab-active' : ''}`}
          onClick={() => {
            setStatusTab('5');
            setCurrentPage(1);
          }}
        >
          Postponed
        </button>
      </div>

      {/* Filters Toolbar */}
      <Card>
        <form
          onSubmit={handleSearchSubmit}
          style={{
            display: 'flex',
            flexWrap: 'wrap',
            gap: '0.75rem',
            alignItems: 'flex-end',
          }}
        >
          {/* Keyword Search */}
          <div style={{ flex: '2 1 200px' }}>
            <label className="innova-form-label" htmlFor="student-search">
              Search by Name or Phone
            </label>
            <input
              id="student-search"
              type="text"
              className="innova-form-control"
              placeholder="e.g. Shakib or 017..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>

          {/* Course Filter */}
          <div style={{ flex: '1 1 180px' }}>
            <label className="innova-form-label" htmlFor="student-course-filter">
              Filter Course
            </label>
            <select
              id="student-course-filter"
              className="innova-form-control"
              value={selectedCourse}
              onChange={(e) => {
                setSelectedCourse(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Courses</option>
              {courses.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
          </div>

          {/* Batch Filter */}
          <div style={{ flex: '1 1 160px' }}>
            <label className="innova-form-label" htmlFor="student-batch-filter">
              Filter Batch
            </label>
            <select
              id="student-batch-filter"
              className="innova-form-control"
              value={selectedBatch}
              onChange={(e) => {
                setSelectedBatch(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Batches</option>
              {batches.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.batchnum}
                </option>
              ))}
            </select>
          </div>

          {/* Buttons */}
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button type="submit" className="innova-btn innova-btn-secondary">
              Search
            </button>
            {(searchQuery || selectedCourse || selectedBatch || statusTab !== 'all') && (
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={handleResetFilters}
              >
                Clear
              </button>
            )}
          </div>
        </form>
      </Card>

      {/* Directory Content */}
      {isLoading ? (
        <Card>
          <div style={{ padding: '2.5rem', textAlign: 'center', color: 'var(--innova-gray-500)' }}>
            <div className="innova-spinner" style={{ margin: '0 auto 0.75rem' }} />
            <div>Loading student directory...</div>
          </div>
        </Card>
      ) : error ? (
        <Alert type="error" title="Directory Error">
          {error}
          <div style={{ marginTop: '0.75rem' }}>
            <button type="button" className="innova-btn innova-btn-sm innova-btn-secondary" onClick={fetchStudents}>
              Retry
            </button>
          </div>
        </Alert>
      ) : students.length === 0 ? (
        <div
          className="innova-card"
          style={{ padding: '3rem 1.5rem', textAlign: 'center', backgroundColor: 'var(--innova-card-bg)' }}
        >
          <div style={{ fontSize: '2.5rem', marginBottom: '0.5rem' }}>🎓</div>
          <h3 style={{ margin: '0 0 0.5rem 0', color: 'var(--innova-gray-800)' }}>No Students Found</h3>
          <p style={{ margin: '0 0 1.25rem 0', color: 'var(--innova-gray-500)', fontSize: '0.9rem' }}>
            No student records matched the specified filter criteria.
          </p>
          {canCreate && (
            <button type="button" className="innova-btn innova-btn-primary" onClick={handleCreateNew}>
              Enroll First Student
            </button>
          )}
        </div>
      ) : (
        <div className="innova-table-container">
          <table className="innova-table">
            <thead>
              <tr>
                <th style={{ width: '48px' }}>#</th>
                <th>Student</th>
                <th>Phone Number</th>
                <th>Batch</th>
                <th>Course</th>
                <th>Status</th>
                <th>Payable Fee</th>
                <th>Admission Date</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {students.map((student, idx) => {
                const rowNum = meta ? ((meta.current_page || 1) - 1) * (meta.per_page || 15) + idx + 1 : idx + 1;
                const statusMeta = STUDENT_STATUS_MAP[student.IsActive] || {
                  label: `Status ${student.IsActive}`,
                  badge: 'innova-badge-gray',
                  description: '',
                };

                const initials = student.sname
                  .split(' ')
                  .map((n) => n[0])
                  .join('')
                  .substring(0, 2)
                  .toUpperCase();

                return (
                  <tr key={student.id}>
                    <td style={{ color: 'var(--innova-gray-500)', fontWeight: 500 }}>
                      {rowNum}
                    </td>
                    <td>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
                        <div
                          style={{
                            width: '34px',
                            height: '34px',
                            borderRadius: '50%',
                            backgroundColor: 'var(--innova-primary-light, #e0e7ff)',
                            color: 'var(--innova-primary, #4338ca)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            fontWeight: 700,
                            fontSize: '0.85rem',
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
                                (e.target as HTMLElement).style.display = 'none';
                              }}
                            />
                          ) : (
                            initials
                          )}
                        </div>
                        <div>
                          <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                            {student.sname}
                          </div>
                          <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                            SID: <code>#{student.id}</code>
                          </span>
                        </div>
                      </div>
                    </td>
                    <td>
                      {student.phone ? (
                        <span style={{ fontWeight: 500 }}>88{student.phone}</span>
                      ) : (
                        <span style={{ color: 'var(--innova-gray-400)', fontSize: '0.8rem' }}>None</span>
                      )}
                    </td>
                    <td>
                      {student.batch?.batchnum ? (
                        <span className="innova-badge innova-badge-gray">
                          {student.batch.batchnum}
                        </span>
                      ) : (
                        <span style={{ color: 'var(--innova-gray-400)', fontSize: '0.8rem' }}>Unassigned</span>
                      )}
                    </td>
                    <td>
                      <div>
                        <div style={{ fontWeight: 500 }}>
                          {student.course?.name || `Course #${student.course_id}`}
                        </div>
                        {student.course?.shortTitle && (
                          <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                            Code: {student.course.shortTitle}
                          </span>
                        )}
                      </div>
                    </td>
                    <td>
                      <button
                        type="button"
                        onClick={() => canEdit && handleStatusChange(student)}
                        style={{
                          background: 'none',
                          border: 'none',
                          padding: 0,
                          cursor: canEdit ? 'pointer' : 'default',
                        }}
                        title={canEdit ? 'Click to change status' : undefined}
                      >
                        <span className={`innova-badge ${statusMeta.badge}`}>
                          {statusMeta.label}
                        </span>
                      </button>
                    </td>
                    <td>
                      <span style={{ fontWeight: 600, color: 'var(--innova-primary)' }}>
                        {Number(student.balance || student.course_fee || 0).toLocaleString()} BDT
                      </span>
                    </td>
                    <td style={{ fontSize: '0.8rem', whiteSpace: 'nowrap' }}>
                      {student.admission_date
                        ? new Date(student.admission_date).toLocaleDateString(undefined, {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                          })
                        : '—'}
                    </td>
                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                      <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                        <button
                          type="button"
                          className="innova-btn innova-btn-sm innova-btn-secondary"
                          onClick={() => handleView(student)}
                          title="View Student Profile"
                        >
                          View
                        </button>

                        {canEdit && (
                          <button
                            type="button"
                            className="innova-btn innova-btn-sm innova-btn-outline"
                            onClick={() => handleEdit(student)}
                            title="Edit Student Information"
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
                              setStudentToDelete(student);
                            }}
                            title="Delete Student Record"
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
      {meta && meta.last_page && meta.last_page > 1 && (
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '0.5rem 0' }}>
          <div style={{ fontSize: '0.875rem', color: 'var(--innova-gray-500)' }}>
            Showing Page {meta.current_page} of {meta.last_page} ({meta.total} total students)
          </div>
          <div style={{ display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              className="innova-btn innova-btn-sm innova-btn-secondary"
              disabled={(meta.current_page || 1) <= 1}
              onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
            >
              Previous
            </button>
            <button
              type="button"
              className="innova-btn innova-btn-sm innova-btn-secondary"
              disabled={(meta.current_page || 1) >= meta.last_page}
              onClick={() => setCurrentPage((p) => p + 1)}
            >
              Next
            </button>
          </div>
        </div>
      )}

      {/* Detail Modal */}
      <StudentDetailModal
        student={detailStudent}
        isOpen={isDetailOpen}
        onClose={() => {
          setIsDetailOpen(false);
          setDetailStudent(null);
        }}
        onEdit={(s) => {
          setIsDetailOpen(false);
          handleEdit(s);
        }}
        onChangeStatus={(s) => {
          setIsDetailOpen(false);
          handleStatusChange(s);
        }}
        canEdit={canEdit}
      />

      {/* Form Modal (Create / Edit) */}
      <StudentFormModal
        student={formStudent}
        isOpen={isFormOpen}
        onClose={() => {
          setIsFormOpen(false);
          setFormStudent(null);
        }}
        onSuccess={() => {
          fetchStudents();
        }}
      />

      {/* Quick Status Modal */}
      <StudentStatusModal
        student={statusStudent}
        isOpen={isStatusOpen}
        onClose={() => {
          setIsStatusOpen(false);
          setStatusStudent(null);
        }}
        onSuccess={() => {
          fetchStudents();
        }}
      />

      {/* Delete Confirmation Modal */}
      {studentToDelete && (
        <div className="innova-modal-backdrop" onClick={() => setStudentToDelete(null)} role="dialog" aria-modal="true">
          <div className="innova-modal" onClick={(e) => e.stopPropagation()} style={{ maxWidth: '480px' }}>
            <div className="innova-modal-header">
              <h2 className="innova-modal-title">Confirm Deletion</h2>
              <button
                type="button"
                className="innova-btn innova-btn-sm innova-btn-secondary"
                onClick={() => setStudentToDelete(null)}
              >
                &times;
              </button>
            </div>
            <div className="innova-modal-body" style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {deleteError && (
                <Alert type="error" title="Cannot Delete Student">
                  {deleteError}
                </Alert>
              )}
              <p style={{ margin: 0, color: 'var(--innova-gray-700)' }}>
                Are you sure you want to delete student <strong>{studentToDelete.sname}</strong> (SID #{studentToDelete.id})?
              </p>
              <div
                style={{
                  padding: '0.75rem 1rem',
                  backgroundColor: 'var(--innova-gray-50)',
                  borderLeft: '4px solid var(--innova-error)',
                  borderRadius: 'var(--innova-radius-sm)',
                  fontSize: '0.85rem',
                  color: 'var(--innova-gray-600)',
                }}
              >
                <strong>Security Guard:</strong> If fee transactions or payment receipts are associated with this student, deletion will be blocked to preserve financial audit integrity.
              </div>
            </div>
            <div className="innova-modal-footer">
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={() => setStudentToDelete(null)}
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
                {isDeleting ? 'Deleting...' : 'Confirm Delete'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

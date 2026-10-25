import React, { useState, useEffect, useCallback } from 'react';
import { Batch, Teacher, BatchFilters, ApiMeta, BATCH_STATUS_MAP, WEEK_DAYS } from '../../../types';
import { batchService, courseService } from '../../../services';
import { useAuth } from '../../../hooks/useAuth';
import { LoadingSpinner, Alert } from '../../../components/common';
import { BatchFormModal, BatchDetailModal } from '../../../components/batches';
import { ApiErrorResponse } from '../../../types/api';

export const BatchListPage: React.FC = () => {
  const { user, hasPermission } = useAuth();
  const canCreate = hasPermission('batch-create') || !!user?.is_admin;
  const canEdit = hasPermission('batch-edit') || !!user?.is_admin;
  const canDelete = hasPermission('batch-delete') || !!user?.is_admin;

  // Data states
  const [batches, setBatches] = useState<Batch[]>([]);
  const [teachers, setTeachers] = useState<Teacher[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [meta, setMeta] = useState<ApiMeta | null>(null);

  // Filters
  const [searchTerm, setSearchTerm] = useState<string>('');
  const [activeTab, setActiveTab] = useState<'all' | 'running' | 'upcoming' | 'completed' | 'postponed'>('all');
  const [currentPage, setCurrentPage] = useState<number>(1);

  // Modals & Selection
  const [isFormOpen, setIsFormOpen] = useState<boolean>(false);
  const [editingBatch, setEditingBatch] = useState<Batch | null>(null);
  const [viewingBatch, setViewingBatch] = useState<Batch | null>(null);
  const [batchToDelete, setBatchToDelete] = useState<Batch | null>(null);
  const [isDeleting, setIsDeleting] = useState<boolean>(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Load teachers for instructor resolution
  useEffect(() => {
    courseService.getTeachers().then(setTeachers).catch(() => {});
  }, []);

  // Fetch batches with current filters
  const fetchBatches = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const filters: BatchFilters = {
        page: currentPage,
        per_page: 15,
      };
      if (searchTerm.trim()) filters.search = searchTerm.trim();
      if (activeTab !== 'all') filters.status = activeTab;

      const result = await batchService.getBatches(filters);
      setBatches(result.data);
      setMeta(result.meta || null);
    } catch (err) {
      const apiErr = err as ApiErrorResponse;
      setError(apiErr.message || 'Failed to load batches.');
    } finally {
      setLoading(false);
    }
  }, [currentPage, searchTerm, activeTab]);

  useEffect(() => {
    fetchBatches();
  }, [fetchBatches]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setCurrentPage(1);
    fetchBatches();
  };

  const handleTabChange = (tab: 'all' | 'running' | 'upcoming' | 'completed' | 'postponed') => {
    setActiveTab(tab);
    setCurrentPage(1);
  };

  const handleCreateNew = () => {
    setEditingBatch(null);
    setIsFormOpen(true);
  };

  const handleEdit = (batch: Batch) => {
    setEditingBatch(batch);
    setIsFormOpen(true);
  };

  const handleView = (batch: Batch) => {
    setViewingBatch(batch);
  };

  const handleFormSuccess = (saved: Batch) => {
    setSuccessMessage(`Batch "${saved.batchnum}" successfully ${editingBatch ? 'updated' : 'created'}.`);
    setTimeout(() => setSuccessMessage(null), 5000);
    fetchBatches();
  };

  const handleDeleteConfirm = async () => {
    if (!batchToDelete) return;
    setIsDeleting(true);
    setDeleteError(null);
    try {
      await batchService.deleteBatch(batchToDelete.id);
      setSuccessMessage(`Batch "${batchToDelete.batchnum}" successfully deleted.`);
      setTimeout(() => setSuccessMessage(null), 5000);
      setBatchToDelete(null);
      fetchBatches();
    } catch (err) {
      const apiErr = err as ApiErrorResponse;
      setDeleteError(apiErr.message || 'Failed to delete batch.');
    } finally {
      setIsDeleting(false);
    }
  };

  // Helper to format days active
  const formatActiveDays = (batch: Batch) => {
    const active = WEEK_DAYS.filter((d) => Number(batch[d.key as keyof Batch] ?? 0) === 1);
    if (active.length === 0) return 'No schedule';
    return active.map((d) => d.label.slice(0, 3)).join(', ');
  };

  return (
    <div>
      {/* Page Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
            <h1 style={{ fontSize: '1.75rem', fontWeight: 700, margin: 0, color: 'var(--innova-gray-900)' }}>
              Batches & Academic Terms
            </h1>
            {meta?.total !== undefined && (
              <span className="innova-badge innova-badge-primary">
                {meta.total} Total
              </span>
            )}
          </div>
          <p style={{ margin: '0.25rem 0 0 0', fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
            Manage cohort schedules, seat capacities, semester milestones, and instructor assignments.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="innova-btn innova-btn-primary"
            onClick={handleCreateNew}
          >
            + Create New Batch
          </button>
        )}
      </div>

      {/* Success Notification */}
      {successMessage && (
        <Alert type="success" title="Success">
          {successMessage}
        </Alert>
      )}

      {/* Status Tabs Navigation */}
      <div className="innova-tabs">
        <button
          type="button"
          className={`innova-tab ${activeTab === 'all' ? 'innova-tab-active' : ''}`}
          onClick={() => handleTabChange('all')}
        >
          All Batches
        </button>
        <button
          type="button"
          className={`innova-tab ${activeTab === 'running' ? 'innova-tab-active' : ''}`}
          onClick={() => handleTabChange('running')}
        >
          Running
        </button>
        <button
          type="button"
          className={`innova-tab ${activeTab === 'upcoming' ? 'innova-tab-active' : ''}`}
          onClick={() => handleTabChange('upcoming')}
        >
          Upcoming
        </button>
        <button
          type="button"
          className={`innova-tab ${activeTab === 'completed' ? 'innova-tab-active' : ''}`}
          onClick={() => handleTabChange('completed')}
        >
          Completed
        </button>
        <button
          type="button"
          className={`innova-tab ${activeTab === 'postponed' ? 'innova-tab-active' : ''}`}
          onClick={() => handleTabChange('postponed')}
        >
          Postponed
        </button>
      </div>

      {/* Search Toolbar */}
      <div
        className="innova-card"
        style={{ padding: '1rem', marginBottom: '1.5rem', backgroundColor: 'var(--innova-card-bg)' }}
      >
        <form onSubmit={handleSearchSubmit} style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
          <div style={{ flex: '1 1 300px' }}>
            <input
              type="text"
              className="innova-form-control"
              placeholder="Search by batch code (e.g. WD-2026-01)..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <button type="submit" className="innova-btn innova-btn-secondary">
            Search
          </button>
          {searchTerm && (
            <button
              type="button"
              className="innova-btn innova-btn-outline"
              onClick={() => {
                setSearchTerm('');
                setCurrentPage(1);
              }}
            >
              Clear
            </button>
          )}
        </form>
      </div>

      {/* Main Table Content */}
      {loading ? (
        <div style={{ padding: '3rem', display: 'flex', justifyContent: 'center' }}>
          <LoadingSpinner size="lg" label="Loading Academic Batches..." />
        </div>
      ) : error ? (
        <Alert type="error" title="Could Not Load Batches">
          {error}
          <div style={{ marginTop: '0.5rem' }}>
            <button
              type="button"
              className="innova-btn innova-btn-sm innova-btn-outline"
              onClick={fetchBatches}
            >
              Retry
            </button>
          </div>
        </Alert>
      ) : batches.length === 0 ? (
        <div
          className="innova-card"
          style={{ padding: '3rem 1.5rem', textAlign: 'center', backgroundColor: 'var(--innova-card-bg)' }}
        >
          <div style={{ fontSize: '2.5rem', marginBottom: '0.5rem' }}>🗓️</div>
          <h3 style={{ margin: '0 0 0.5rem 0', color: 'var(--innova-gray-800)' }}>No Batches Found</h3>
          <p style={{ margin: '0 0 1.25rem 0', color: 'var(--innova-gray-500)', fontSize: '0.9rem' }}>
            {searchTerm
              ? `No batch records matched "${searchTerm}".`
              : `There are currently no batches in "${activeTab}" status.`}
          </p>
          {canCreate && (
            <button type="button" className="innova-btn innova-btn-primary" onClick={handleCreateNew}>
              Create New Batch
            </button>
          )}
        </div>
      ) : (
        <div className="innova-table-container">
          <table className="innova-table">
            <thead>
              <tr>
                <th style={{ width: '48px' }}>#</th>
                <th>Batch Number</th>
                <th>Instructor</th>
                <th>Seats</th>
                <th>Timeline</th>
                <th>Weekly Schedule</th>
                <th>Status</th>
                <th style={{ textAlign: 'right' }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {batches.map((batch, idx) => {
                const rowNum = meta ? ((meta.current_page || 1) - 1) * (meta.per_page || 15) + idx + 1 : idx + 1;
                const statusMeta = BATCH_STATUS_MAP[batch.IsActive] || {
                  label: 'Unknown',
                  badge: 'innova-badge-gray',
                  color: '#6b7280',
                };

                return (
                  <tr key={batch.id}>
                    <td style={{ color: 'var(--innova-gray-500)', fontWeight: 500 }}>
                      {rowNum}
                    </td>
                    <td>
                      <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                        {batch.batchnum}
                      </div>
                    </td>
                    <td>
                      {batch.teacher?.name ? (
                        <div>
                          <div style={{ fontWeight: 500 }}>{batch.teacher.name}</div>
                          {batch.teacher.designation && (
                            <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                              {batch.teacher.designation}
                            </span>
                          )}
                        </div>
                      ) : (
                        <span style={{ color: 'var(--innova-gray-400)', fontSize: '0.8rem' }}>Unassigned</span>
                      )}
                    </td>
                    <td>
                      <span style={{ fontWeight: 600, color: 'var(--innova-primary)' }}>
                        {batch.totalseat} Seats
                      </span>
                    </td>
                    <td style={{ fontSize: '0.8rem', whiteSpace: 'nowrap' }}>
                      <div>Start: {batch.startdate ? new Date(batch.startdate).toLocaleDateString() : 'TBD'}</div>
                      <div style={{ color: 'var(--innova-gray-500)' }}>
                        End: {batch.enddate ? new Date(batch.enddate).toLocaleDateString() : 'TBD'}
                      </div>
                    </td>
                    <td>
                      <span className="innova-badge innova-badge-gray" style={{ fontSize: '0.75rem' }}>
                        {formatActiveDays(batch)}
                      </span>
                    </td>
                    <td>
                      <span className={`innova-badge ${statusMeta.badge}`}>
                        {statusMeta.label}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                      <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                        <button
                          type="button"
                          className="innova-btn innova-btn-sm innova-btn-secondary"
                          onClick={() => handleView(batch)}
                          title="View Batch Details"
                        >
                          View
                        </button>

                        {canEdit && (
                          <button
                            type="button"
                            className="innova-btn innova-btn-sm innova-btn-outline"
                            onClick={() => handleEdit(batch)}
                            title="Edit Batch"
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
                              setBatchToDelete(batch);
                            }}
                            title="Delete Batch"
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
            Showing page {meta.current_page} of {meta.last_page} ({meta.total} batches total)
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
      <BatchFormModal
        isOpen={isFormOpen}
        onClose={() => setIsFormOpen(false)}
        onSuccess={handleFormSuccess}
        batch={editingBatch}
        teachers={teachers}
      />

      <BatchDetailModal
        isOpen={!!viewingBatch}
        onClose={() => setViewingBatch(null)}
        batch={viewingBatch}
        canEdit={canEdit}
        onEdit={(b) => {
          setViewingBatch(null);
          handleEdit(b);
        }}
      />

      {/* Delete Confirmation Modal */}
      {batchToDelete && (
        <div className="innova-modal-backdrop" onClick={() => !isDeleting && setBatchToDelete(null)} role="dialog" aria-modal="true">
          <div className="innova-modal" onClick={(e) => e.stopPropagation()}>
            <div className="innova-modal-header">
              <h2 className="innova-modal-title" style={{ color: 'var(--innova-error)' }}>
                Confirm Batch Deletion
              </h2>
              <button
                type="button"
                className="innova-btn innova-btn-sm innova-btn-secondary"
                onClick={() => setBatchToDelete(null)}
                disabled={isDeleting}
              >
                &times;
              </button>
            </div>

            <div className="innova-modal-body">
              {deleteError ? (
                <Alert type="error" title="Cannot Delete Batch">
                  {deleteError}
                </Alert>
              ) : (
                <p style={{ margin: 0, fontSize: '0.95rem', color: 'var(--innova-gray-700)' }}>
                  Are you sure you want to permanently delete batch <strong>{batchToDelete.batchnum}</strong>?
                </p>
              )}
              <p style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)', marginTop: '0.5rem' }}>
                Note: In accordance with academic integrity rules, batches with assigned students cannot be deleted.
              </p>
            </div>

            <div className="innova-modal-footer">
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={() => setBatchToDelete(null)}
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
                {isDeleting ? <LoadingSpinner size="sm" label="Deleting..." /> : 'Yes, Delete Batch'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

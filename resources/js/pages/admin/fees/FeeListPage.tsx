import React, { useState, useEffect, useCallback } from 'react';
import {
  FeeTransaction,
  FeeFinancialSummary,
  FeeFilters,
  Course,
  Batch,
  Student,
  ApiMeta,
  FEE_STATUS_MAP,
  FEE_CHANNEL_MAP,
  FEE_TYPE_MAP,
} from '../../../types';
import {
  feeService,
  courseService,
  batchService,
  studentService,
} from '../../../services';
import {
  FeeCollectionModal,
  FeeTransactionDetailModal,
  FeeTransactionEditModal,
  FeeStatusModal,
  PrintableReceiptModal,
  StudentLedgerCard,
  BkashReconciliationModal,
} from '../../../components/fees';
import { Alert, LoadingSpinner } from '../../../components/common';

type MainTab = 'transactions' | 'ledgers' | 'counter' | 'reconciliation';

export const FeeListPage: React.FC = () => {
  // Navigation
  const [activeTab, setActiveTab] = useState<MainTab>('transactions');

  // Transactions State (feelog)
  const [transactions, setTransactions] = useState<FeeTransaction[]>([]);
  const [meta, setMeta] = useState<ApiMeta | null>(null);
  const [isLoadingTrx, setIsLoadingTrx] = useState<boolean>(true);
  const [trxError, setTrxError] = useState<string | null>(null);

  // Filters for Transactions Log
  const [channelFilter, setChannelFilter] = useState<string>('all');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const [startDateFilter, setStartDateFilter] = useState<string>('');
  const [endDateFilter, setEndDateFilter] = useState<string>('');
  const [searchFilter, setSearchFilter] = useState<string>('');
  const [page, setPage] = useState<number>(1);

  // Students Directory State (for Ledgers tab)
  const [students, setStudents] = useState<Student[]>([]);
  const [studentMeta, setStudentMeta] = useState<ApiMeta | null>(null);
  const [isLoadingStudents, setIsLoadingStudents] = useState<boolean>(false);
  const [studentSearch, setStudentSearch] = useState<string>('');
  const [selectedCourseId, setSelectedCourseId] = useState<string>('');
  const [selectedBatchId, setSelectedBatchId] = useState<string>('');
  const [studentPage, setStudentPage] = useState<number>(1);
  const [activeLedgerStudentId, setActiveLedgerStudentId] = useState<number | null>(null);

  // Catalogs
  const [courses, setCourses] = useState<Course[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);

  // Modals State
  const [isCollectModalOpen, setIsCollectModalOpen] = useState<boolean>(false);
  const [preselectedStudentId, setPreselectedStudentId] = useState<number | null>(null);

  const [selectedTransaction, setSelectedTransaction] = useState<FeeTransaction | null>(null);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState<boolean>(false);

  const [editTransaction, setEditTransaction] = useState<FeeTransaction | null>(null);
  const [isEditModalOpen, setIsEditModalOpen] = useState<boolean>(false);

  const [statusTransaction, setStatusTransaction] = useState<FeeTransaction | null>(null);
  const [isStatusModalOpen, setIsStatusModalOpen] = useState<boolean>(false);

  const [receiptTrxId, setReceiptTrxId] = useState<number | null>(null);
  const [isReceiptModalOpen, setIsReceiptModalOpen] = useState<boolean>(false);

  const [isReconModalOpen, setIsReconModalOpen] = useState<boolean>(false);

  // Success Feedback
  const [successToast, setSuccessToast] = useState<string | null>(null);

  // Load Catalogs on Mount
  useEffect(() => {
    let isMounted = true;
    Promise.all([
      courseService.getCourses().catch(() => ({ data: [] })),
      batchService.getBatches().catch(() => ({ data: [] })),
    ]).then(([courseRes, batchRes]) => {
      if (isMounted) {
        setCourses(courseRes.data || []);
        setBatches(batchRes.data || []);
      }
    });

    return () => {
      isMounted = false;
    };
  }, []);

  // Fetch Transactions Log
  const loadTransactions = useCallback(async () => {
    setIsLoadingTrx(true);
    setTrxError(null);

    const filters: FeeFilters = {
      page,
      per_page: 15,
    };

    if (channelFilter !== 'all') filters.channel = Number(channelFilter);
    if (statusFilter !== 'all') filters.status = Number(statusFilter);
    if (typeFilter !== 'all') filters.payment_type = Number(typeFilter);
    if (startDateFilter) filters.start_date = startDateFilter;
    if (endDateFilter) filters.end_date = endDateFilter;
    if (searchFilter.trim()) filters.trx_id = searchFilter.trim();

    try {
      const res = await feeService.getTransactions(filters);
      setTransactions(res.data);
      setMeta(res.meta);
    } catch (err: unknown) {
      setTrxError(err instanceof Error ? err.message : 'Failed to retrieve fee transactions.');
    } finally {
      setIsLoadingTrx(false);
    }
  }, [page, channelFilter, statusFilter, typeFilter, startDateFilter, endDateFilter, searchFilter]);

  useEffect(() => {
    loadTransactions();
  }, [loadTransactions]);

  // Fetch Students Directory (Ledgers Tab)
  const loadStudents = useCallback(async () => {
    setIsLoadingStudents(true);
    try {
      const res = await studentService.getStudents({
        search: studentSearch.trim() || undefined,
        course_id: selectedCourseId ? Number(selectedCourseId) : undefined,
        batch_id: selectedBatchId ? Number(selectedBatchId) : undefined,
        page: studentPage,
        per_page: 12,
      });
      setStudents(res.data);
      setStudentMeta(res.meta || null);
    } catch {
      // silent
    } finally {
      setIsLoadingStudents(false);
    }
  }, [studentSearch, selectedCourseId, selectedBatchId, studentPage]);

  useEffect(() => {
    if (activeTab === 'ledgers') {
      loadStudents();
    }
  }, [activeTab, loadStudents]);

  // Metric Computations from Loaded Dataset
  const totalApprovedAmount = transactions
    .filter((t) => Number(t.status) === 0)
    .reduce((sum, t) => sum + parseFloat(String(t.paidamount) || '0'), 0);

  const pendingApprovalsCount = transactions.filter((t) => Number(t.status) === 1).length;
  const cashCount = transactions.filter((t) => Number(t.channel) === 1).length;
  const bkashCount = transactions.filter((t) => Number(t.channel) === 2).length;

  // Handlers
  const handleOpenReceipt = (id: number) => {
    setReceiptTrxId(id);
    setIsReceiptModalOpen(true);
  };

  const handleOpenCollectForStudent = (studentId: number) => {
    setPreselectedStudentId(studentId);
    setIsCollectModalOpen(true);
  };

  const handlePaymentSuccess = (trx: FeeTransaction, summary: FeeFinancialSummary) => {
    setSuccessToast(
      `Payment of ৳${Number(trx.paidamount).toLocaleString()} recorded successfully for student #${summary.student_id}. Remaining due: ৳${Number(summary.due_amount).toLocaleString()}.`
    );
    loadTransactions();
    if (activeLedgerStudentId) {
      setActiveLedgerStudentId(summary.student_id);
    }
    // Offer receipt preview
    handleOpenReceipt(trx.id);
  };

  const handleTransactionUpdated = (updated: FeeTransaction) => {
    setSuccessToast(`Transaction #${updated.id} updated successfully.`);
    loadTransactions();
  };

  const handleStatusUpdated = (updated: FeeTransaction) => {
    const statusLabel = Number(updated.status) === 0 ? 'Approved' : 'Pending';
    setSuccessToast(`Transaction #${updated.id} status changed to ${statusLabel}.`);
    loadTransactions();
  };

  return (
    <div className="innova-admin-page">
      {/* Page Header */}
      <div className="innova-page-header" style={{ marginBottom: '20px' }}>
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <h1 className="innova-page-title" style={{ margin: 0 }}>
              Fees & Payment Management
            </h1>
            <span className="innova-badge innova-badge-success">Phase 3.7</span>
          </div>
          <p className="innova-page-subtitle" style={{ margin: '4px 0 0' }}>
            Tuition fee counter collections, financial ledgers, bKash reconciliation, and printable vouchers
          </p>
        </div>

        <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
          <button
            type="button"
            className="innova-btn innova-btn-secondary"
            onClick={() => setIsReconModalOpen(true)}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10" />
              <polyline points="12 6 12 12 16 14" />
            </svg>
            bKash Verify
          </button>

          <button
            type="button"
            className="innova-btn innova-btn-primary"
            onClick={() => {
              setPreselectedStudentId(null);
              setIsCollectModalOpen(true);
            }}
            style={{ background: 'var(--color-success-600, #16a34a)', borderColor: 'var(--color-success-600, #16a34a)' }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="5" x2="12" y2="19" />
              <line x1="5" y1="12" x2="19" y2="12" />
            </svg>
            Collect Payment
          </button>
        </div>
      </div>

      {/* Success Notification Toast */}
      {successToast && (
        <Alert type="success">
          {successToast}
        </Alert>
      )}

      {/* Metric Summary Cards */}
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(210px, 1fr))',
          gap: '14px',
          marginBottom: '22px',
        }}
      >
        <div className="innova-stat-card" style={{ padding: '16px 18px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>
              Current Page Approved
            </span>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(34, 197, 94, 0.1)',
                color: 'var(--color-success-600)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              ৳
            </div>
          </div>
          <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--color-text-main)', marginTop: '6px' }}>
            ৳{totalApprovedAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
            Approved fee receipts in current view
          </div>
        </div>

        <div className="innova-stat-card" style={{ padding: '16px 18px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>
              Pending Approvals
            </span>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(234, 179, 8, 0.1)',
                color: 'var(--color-warning-600)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              !
            </div>
          </div>
          <div style={{ fontSize: '24px', fontWeight: 800, color: pendingApprovalsCount > 0 ? 'var(--color-warning-600)' : 'var(--color-text-main)', marginTop: '6px' }}>
            {pendingApprovalsCount}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
            Online payments awaiting confirmation
          </div>
        </div>

        <div className="innova-stat-card" style={{ padding: '16px 18px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>
              Total Transactions
            </span>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(99, 102, 241, 0.1)',
                color: 'var(--color-primary-600)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              #
            </div>
          </div>
          <div style={{ fontSize: '24px', fontWeight: 800, color: 'var(--color-text-main)', marginTop: '6px' }}>
            {meta?.total ?? transactions.length}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-text-muted)', marginTop: '2px' }}>
            Historical financial ledger records
          </div>
        </div>

        <div className="innova-stat-card" style={{ padding: '16px 18px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--color-text-muted)', textTransform: 'uppercase' }}>
              Channel Mix
            </span>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                background: 'rgba(14, 165, 233, 0.1)',
                color: 'var(--color-info-600)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
            >
              %
            </div>
          </div>
          <div style={{ fontSize: '18px', fontWeight: 700, color: 'var(--color-text-main)', marginTop: '10px' }}>
            Cash: {cashCount} | bKash: {bkashCount}
          </div>
          <div style={{ fontSize: '12px', color: 'var(--color-text-muted)', marginTop: '4px' }}>
            Direct counter vs gateway split
          </div>
        </div>
      </div>

      {/* Main Module Tabs */}
      <div className="innova-tabs" style={{ marginBottom: '20px' }}>
        <button
          type="button"
          className={`innova-tab ${activeTab === 'transactions' ? 'innova-tab-active' : ''}`}
          onClick={() => setActiveTab('transactions')}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="8" y1="6" x2="21" y2="6" />
            <line x1="8" y1="12" x2="21" y2="12" />
            <line x1="8" y1="18" x2="21" y2="18" />
            <line x1="3" y1="6" x2="3.01" y2="6" />
            <line x1="3" y1="12" x2="3.01" y2="12" />
            <line x1="3" y1="18" x2="3.01" y2="18" />
          </svg>
          Transactions Log ({meta?.total ?? transactions.length})
        </button>

        <button
          type="button"
          className={`innova-tab ${activeTab === 'ledgers' ? 'innova-tab-active' : ''}`}
          onClick={() => setActiveTab('ledgers')}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          Student Balances & Ledgers
        </button>

        <button
          type="button"
          className={`innova-tab ${activeTab === 'reconciliation' ? 'innova-tab-active' : ''}`}
          onClick={() => setActiveTab('reconciliation')}
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 6v6l4 2" />
          </svg>
          bKash Reconciliation
        </button>
      </div>

      {/* TAB 1: TRANSACTIONS LOG */}
      {activeTab === 'transactions' && (
        <div className="innova-card" style={{ padding: '20px' }}>
          {/* Filters Bar */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))',
              gap: '12px',
              marginBottom: '18px',
            }}
          >
            <div>
              <label className="innova-label" style={{ fontSize: '12px' }}>Search TrxID / Ref</label>
              <input
                type="text"
                className="innova-input"
                placeholder="TrxID or notes..."
                value={searchFilter}
                onChange={(e) => {
                  setSearchFilter(e.target.value);
                  setPage(1);
                }}
              />
            </div>

            <div>
              <label className="innova-label" style={{ fontSize: '12px' }}>Channel</label>
              <select
                className="innova-input"
                value={channelFilter}
                onChange={(e) => {
                  setChannelFilter(e.target.value);
                  setPage(1);
                }}
              >
                <option value="all">All Channels</option>
                <option value="1">Cash Counter (1)</option>
                <option value="2">bKash Gateway (2)</option>
              </select>
            </div>

            <div>
              <label className="innova-label" style={{ fontSize: '12px' }}>Status</label>
              <select
                className="innova-input"
                value={statusFilter}
                onChange={(e) => {
                  setStatusFilter(e.target.value);
                  setPage(1);
                }}
              >
                <option value="all">All Statuses</option>
                <option value="0">Approved (Active)</option>
                <option value="1">Pending Approval</option>
              </select>
            </div>

            <div>
              <label className="innova-label" style={{ fontSize: '12px' }}>Payment Type</label>
              <select
                className="innova-input"
                value={typeFilter}
                onChange={(e) => {
                  setTypeFilter(e.target.value);
                  setPage(1);
                }}
              >
                <option value="all">All Types</option>
                <option value="1">Course Fee</option>
                <option value="2">Registration Fee</option>
              </select>
            </div>

            <div>
              <label className="innova-label" style={{ fontSize: '12px' }}>From Date</label>
              <input
                type="date"
                className="innova-input"
                value={startDateFilter}
                onChange={(e) => {
                  setStartDateFilter(e.target.value);
                  setPage(1);
                }}
              />
            </div>

            <div>
              <label className="innova-label" style={{ fontSize: '12px' }}>To Date</label>
              <input
                type="date"
                className="innova-input"
                value={endDateFilter}
                onChange={(e) => {
                  setEndDateFilter(e.target.value);
                  setPage(1);
                }}
              />
            </div>
          </div>

          {trxError && (
            <Alert type="error">
              {trxError}
            </Alert>
          )}

          {/* Transactions Table */}
          {isLoadingTrx ? (
            <div style={{ padding: '60px', textAlign: 'center' }}>
              <LoadingSpinner />
              <div style={{ marginTop: '12px', fontSize: '14px', color: 'var(--color-text-muted)' }}>
                Loading fee transactions...
              </div>
            </div>
          ) : transactions.length === 0 ? (
            <div style={{ padding: '48px', textAlign: 'center', color: 'var(--color-text-muted)' }}>
              No fee transactions match the selected criteria.
            </div>
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table className="innova-table" style={{ width: '100%', fontSize: '13px' }}>
                <thead>
                  <tr>
                    <th>Tnx #</th>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Channel</th>
                    <th>Type</th>
                    <th>Amount Paid</th>
                    <th>Status</th>
                    <th>Remarks / TrxID</th>
                    <th style={{ textAlign: 'right' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {transactions.map((trx) => {
                    const statusConfig = FEE_STATUS_MAP[Number(trx.status) ?? 0] || {
                      label: 'Approved',
                      badgeClass: 'innova-badge-success',
                    };
                    const channelConfig = FEE_CHANNEL_MAP[Number(trx.channel) ?? 1] || {
                      label: 'Cash',
                      badgeClass: 'innova-badge-neutral',
                    };
                    const typeName = FEE_TYPE_MAP[Number(trx.paymentType) ?? 1] || 'Course Fee';

                    return (
                      <tr key={trx.id}>
                        <td style={{ fontWeight: 700 }}>#{trx.id}</td>
                        <td style={{ whiteSpace: 'nowrap' }}>{trx.paiddate}</td>
                        <td>
                          <div style={{ fontWeight: 600, color: 'var(--color-text-main)' }}>
                            {trx.student?.sname || `Student #${trx.student_id}`}
                          </div>
                          {trx.student?.phone && (
                            <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>
                              {trx.student.phone}
                            </div>
                          )}
                        </td>
                        <td>
                          <span className={`innova-badge ${channelConfig.badgeClass}`}>
                            {channelConfig.label}
                          </span>
                        </td>
                        <td>
                          <span className="innova-badge innova-badge-neutral">{typeName}</span>
                        </td>
                        <td style={{ fontWeight: 700, color: 'var(--color-success-700, #15803d)', fontSize: '14px' }}>
                          ৳{Number(trx.paidamount).toLocaleString('en-US', { minimumFractionDigits: 2 })}
                        </td>
                        <td>
                          <span className={`innova-badge ${statusConfig.badgeClass}`}>
                            {statusConfig.label}
                          </span>
                        </td>
                        <td style={{ maxWidth: '180px', textOverflow: 'ellipsis', overflow: 'hidden', whiteSpace: 'nowrap' }}>
                          {trx.trxID ? (
                            <span style={{ fontFamily: 'monospace', fontWeight: 600, color: '#be185d' }}>
                              {trx.trxID}
                            </span>
                          ) : (
                            trx.paidremark || '—'
                          )}
                        </td>
                        <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                          <div style={{ display: 'flex', gap: '6px', justifyContent: 'flex-end' }}>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setSelectedTransaction(trx);
                                setIsDetailModalOpen(true);
                              }}
                              title="Inspect Details"
                            >
                              View
                            </button>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => handleOpenReceipt(trx.id)}
                              title="Print Payslip Voucher"
                            >
                              Receipt
                            </button>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setStatusTransaction(trx);
                                setIsStatusModalOpen(true);
                              }}
                              title="Toggle Approval Status"
                            >
                              Status
                            </button>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setEditTransaction(trx);
                                setIsEditModalOpen(true);
                              }}
                              title="Edit Transaction Notes"
                            >
                              Edit
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {meta && (meta.last_page ?? 1) > 1 && (
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                marginTop: '16px',
                paddingTop: '12px',
                borderTop: '1px solid var(--color-border)',
              }}
            >
              <div style={{ fontSize: '13px', color: 'var(--color-text-muted)' }}>
                Page {meta.current_page} of {meta.last_page} ({meta.total} total transactions)
              </div>
              <div style={{ display: 'flex', gap: '8px' }}>
                <button
                  type="button"
                  className="innova-btn innova-btn-sm innova-btn-secondary"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                >
                  Previous
                </button>
                <button
                  type="button"
                  className="innova-btn innova-btn-sm innova-btn-secondary"
                  disabled={page >= (meta.last_page ?? 1)}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: STUDENT BALANCES & LEDGERS */}
      {activeTab === 'ledgers' && (
        <div style={{ display: 'grid', gridTemplateColumns: activeLedgerStudentId ? '1.1fr 1fr' : '1fr', gap: '20px' }}>
          {/* Left: Students Table */}
          <div className="innova-card" style={{ padding: '20px' }}>
            <div style={{ display: 'flex', gap: '12px', marginBottom: '16px', flexWrap: 'wrap' }}>
              <input
                type="text"
                className="innova-input"
                style={{ flex: 1, minWidth: '180px' }}
                placeholder="Search students by name or phone..."
                value={studentSearch}
                onChange={(e) => {
                  setStudentSearch(e.target.value);
                  setStudentPage(1);
                }}
              />
              <select
                className="innova-input"
                style={{ width: '180px' }}
                value={selectedCourseId}
                onChange={(e) => {
                  setSelectedCourseId(e.target.value);
                  setStudentPage(1);
                }}
              >
                <option value="">All Courses</option>
                {courses.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
              <select
                className="innova-input"
                style={{ width: '160px' }}
                value={selectedBatchId}
                onChange={(e) => {
                  setSelectedBatchId(e.target.value);
                  setStudentPage(1);
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

            {isLoadingStudents ? (
              <div style={{ padding: '48px', textAlign: 'center' }}>
                <LoadingSpinner />
                <div style={{ marginTop: '10px', fontSize: '13px', color: 'var(--color-text-muted)' }}>
                  Loading student roster...
                </div>
              </div>
            ) : students.length === 0 ? (
              <div style={{ padding: '36px', textAlign: 'center', color: 'var(--color-text-muted)' }}>
                No students found matching search.
              </div>
            ) : (
              <div style={{ overflowX: 'auto' }}>
                <table className="innova-table" style={{ width: '100%', fontSize: '13px' }}>
                  <thead>
                    <tr>
                      <th>Candidate</th>
                      <th>Course / Batch</th>
                      <th>Net Fee</th>
                      <th>Paid</th>
                      <th>Due</th>
                      <th style={{ textAlign: 'right' }}>Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {students.map((stu) => {
                      const netFee = parseFloat(String(stu.balance) || '0');
                      const courseFee = parseFloat(String(stu.course_fee) || '0');
                      const isSelected = activeLedgerStudentId === stu.id;

                      return (
                        <tr
                          key={stu.id}
                          style={{
                            background: isSelected ? 'var(--color-bg-hover, #f1f5f9)' : 'transparent',
                            cursor: 'pointer',
                          }}
                          onClick={() => setActiveLedgerStudentId(stu.id)}
                        >
                          <td>
                            <div style={{ fontWeight: 600 }}>{stu.sname}</div>
                            <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>{stu.phone}</div>
                          </td>
                          <td>
                            <div>{stu.course?.name || `Course #${stu.course_id}`}</div>
                            <div style={{ fontSize: '11px', color: 'var(--color-text-muted)' }}>
                              {stu.batch?.batchnum || `Batch #${stu.batch_id}`}
                            </div>
                          </td>
                          <td style={{ fontWeight: 600 }}>
                            ৳{netFee.toLocaleString()}
                            {courseFee > netFee && (
                              <div style={{ fontSize: '10px', color: 'var(--color-primary-600)' }}>
                                (Disc: ৳{(courseFee - netFee).toLocaleString()})
                              </div>
                            )}
                          </td>
                          <td>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-ghost"
                              onClick={(e) => {
                                e.stopPropagation();
                                setActiveLedgerStudentId(stu.id);
                              }}
                            >
                              Inspect
                            </button>
                          </td>
                          <td>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-primary"
                              onClick={(e) => {
                                e.stopPropagation();
                                handleOpenCollectForStudent(stu.id);
                              }}
                              style={{ background: 'var(--color-success-600, #16a34a)', borderColor: 'var(--color-success-600, #16a34a)' }}
                            >
                              Collect
                            </button>
                          </td>
                          <td style={{ textAlign: 'right' }}>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={(e) => {
                                e.stopPropagation();
                                setActiveLedgerStudentId(stu.id);
                              }}
                            >
                              Ledger &rarr;
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            {/* Students Pagination */}
            {studentMeta && (studentMeta.last_page ?? 1) > 1 && (
              <div
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  marginTop: '14px',
                }}
              >
                <div style={{ fontSize: '12px', color: 'var(--color-text-muted)' }}>
                  Page {studentMeta.current_page} of {studentMeta.last_page}
                </div>
                <div style={{ display: 'flex', gap: '6px' }}>
                  <button
                    type="button"
                    className="innova-btn innova-btn-sm innova-btn-secondary"
                    disabled={studentPage <= 1}
                    onClick={() => setStudentPage((p) => Math.max(1, p - 1))}
                  >
                    Prev
                  </button>
                  <button
                    type="button"
                    className="innova-btn innova-btn-sm innova-btn-secondary"
                    disabled={studentPage >= (studentMeta.last_page ?? 1)}
                    onClick={() => setStudentPage((p) => p + 1)}
                  >
                    Next
                  </button>
                </div>
              </div>
            )}
          </div>

          {/* Right: Detailed Student Ledger Card */}
          {activeLedgerStudentId && (
            <div>
              <StudentLedgerCard
                studentId={activeLedgerStudentId}
                onCollectFee={handleOpenCollectForStudent}
                onViewReceipt={handleOpenReceipt}
              />
            </div>
          )}
        </div>
      )}

      {/* TAB 3: RECONCILIATION */}
      {activeTab === 'reconciliation' && (
        <div className="innova-card" style={{ padding: '24px', maxWidth: '720px', margin: '0 auto' }}>
          <h2 style={{ fontSize: '18px', fontWeight: 700, margin: '0 0 8px' }}>
            bKash Gateway Server Verification Tool
          </h2>
          <p style={{ fontSize: '13px', color: 'var(--color-text-muted)', margin: '0 0 20px' }}>
            Perform direct server-to-server gateway queries to verify payment status, customer MSISDN, and captured amount.
          </p>
          <button
            type="button"
            className="innova-btn innova-btn-primary"
            onClick={() => setIsReconModalOpen(true)}
          >
            Launch bKash Query Tool
          </button>
        </div>
      )}

      {/* Legacy Coexistence Notice */}
      <div
        style={{
          marginTop: '28px',
          padding: '14px 18px',
          background: 'var(--color-bg-subtle, #f8fafc)',
          border: '1px solid var(--color-border)',
          borderRadius: '8px',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          fontSize: '13px',
          color: 'var(--color-text-muted)',
        }}
      >
        <div>
          <strong>Legacy Blade Portal Coexistence:</strong> Classic payment views remain active at{' '}
          <a href="/admin/fees" className="innova-link" target="_blank" rel="noopener noreferrer">
            /admin/fees
          </a>{' '}
          and fee log at{' '}
          <a href="/admin/fees/feelog" className="innova-link" target="_blank" rel="noopener noreferrer">
            /admin/fees/feelog
          </a>
          .
        </div>
        <span className="innova-badge innova-badge-neutral">Dual Mode Active</span>
      </div>

      {/* Modals */}
      <FeeCollectionModal
        isOpen={isCollectModalOpen}
        onClose={() => {
          setIsCollectModalOpen(false);
          setPreselectedStudentId(null);
        }}
        onSuccess={handlePaymentSuccess}
        initialStudentId={preselectedStudentId}
      />

      <FeeTransactionDetailModal
        isOpen={isDetailModalOpen}
        onClose={() => {
          setIsDetailModalOpen(false);
          setSelectedTransaction(null);
        }}
        transaction={selectedTransaction}
        onOpenReceipt={handleOpenReceipt}
        onEdit={(trx) => {
          setEditTransaction(trx);
          setIsEditModalOpen(true);
        }}
        onStatusChange={(trx) => {
          setStatusTransaction(trx);
          setIsStatusModalOpen(true);
        }}
      />

      <FeeTransactionEditModal
        isOpen={isEditModalOpen}
        onClose={() => {
          setIsEditModalOpen(false);
          setEditTransaction(null);
        }}
        transaction={editTransaction}
        onSuccess={handleTransactionUpdated}
      />

      <FeeStatusModal
        isOpen={isStatusModalOpen}
        onClose={() => {
          setIsStatusModalOpen(false);
          setStatusTransaction(null);
        }}
        transaction={statusTransaction}
        onSuccess={handleStatusUpdated}
      />

      <PrintableReceiptModal
        isOpen={isReceiptModalOpen}
        onClose={() => {
          setIsReceiptModalOpen(false);
          setReceiptTrxId(null);
        }}
        transactionId={receiptTrxId}
      />

      <BkashReconciliationModal
        isOpen={isReconModalOpen}
        onClose={() => setIsReconModalOpen(false)}
      />
    </div>
  );
};

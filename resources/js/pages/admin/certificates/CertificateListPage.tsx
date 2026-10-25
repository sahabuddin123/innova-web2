import React, { useState, useEffect, useCallback } from 'react';
import {
  CertificateIcbd,
  CertificateBteb,
  CertificateSummaryReport,
  Course,
  Batch,
  CertificateSessionBrief,
  ApiMeta,
  ICBD_STATUS_MAP,
  BTEB_STATUS_MAP,
} from '../../../types';
import {
  certificateService,
  courseService,
  batchService,
} from '../../../services';
import {
  CertificateDetailModal,
  IssueCertificateModal,
  CertificateFormModal,
  CertificateStatusModal,
  BtebCertificateModal,
  EligibilityCheckerCard,
  PublicCertificateVerifier,
} from '../../../components/certificates';
import { Alert, LoadingSpinner } from '../../../components/common';

type MainTab = 'icbd' | 'bteb' | 'eligibility' | 'verify';
type IcbdStatusTab = 'all' | '1' | '2' | '3' | '4';

export const CertificateListPage: React.FC = () => {
  // Navigation tab
  const [activeTab, setActiveTab] = useState<MainTab>('icbd');

  // Summary Metrics
  const [summary, setSummary] = useState<CertificateSummaryReport | null>(null);

  // ICBD Certificates State
  const [icbdCerts, setIcbdCerts] = useState<CertificateIcbd[]>([]);
  const [icbdMeta, setIcbdMeta] = useState<ApiMeta | null>(null);
  const [icbdLoading, setIcbdLoading] = useState<boolean>(true);
  const [icbdError, setIcbdError] = useState<string | null>(null);
  const [icbdStatusTab, setIcbdStatusTab] = useState<IcbdStatusTab>('all');
  const [icbdSearch, setIcbdSearch] = useState<string>('');
  const [icbdCourseFilter, setIcbdCourseFilter] = useState<string>('');
  const [icbdBatchFilter, setIcbdBatchFilter] = useState<string>('');
  const [icbdPage, setIcbdPage] = useState<number>(1);

  // BTEB Certificates State
  const [btebCerts, setBtebCerts] = useState<CertificateBteb[]>([]);
  const [btebMeta, setBtebMeta] = useState<ApiMeta | null>(null);
  const [btebLoading, setBtebLoading] = useState<boolean>(false);
  const [btebError, setBtebError] = useState<string | null>(null);
  const [btebSearch, setBtebSearch] = useState<string>('');
  const [btebSessionFilter, setBtebSessionFilter] = useState<string>('');
  const [btebStatusFilter, setBtebStatusFilter] = useState<string>('all');
  const [btebPage, setBtebPage] = useState<number>(1);

  // Catalogs
  const [courses, setCourses] = useState<Course[]>([]);
  const [batches, setBatches] = useState<Batch[]>([]);
  const [sessions, setSessions] = useState<CertificateSessionBrief[]>([]);

  // Modals State
  const [detailCert, setDetailCert] = useState<CertificateIcbd | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState<boolean>(false);

  const [isIssueOpen, setIsIssueOpen] = useState<boolean>(false);
  const [preselectedStudentId, setPreselectedStudentId] = useState<number | null>(null);

  const [editIcbdCert, setEditIcbdCert] = useState<CertificateIcbd | null>(null);
  const [isEditIcbdOpen, setIsEditIcbdOpen] = useState<boolean>(false);

  const [statusIcbdCert, setStatusIcbdCert] = useState<CertificateIcbd | null>(null);
  const [isStatusIcbdOpen, setIsStatusIcbdOpen] = useState<boolean>(false);

  const [btebModalCert, setBtebModalCert] = useState<CertificateBteb | null>(null);
  const [isBtebModalOpen, setIsBtebModalOpen] = useState<boolean>(false);

  // Delete Confirm State
  const [deleteConfirm, setDeleteConfirm] = useState<{
    type: 'icbd' | 'bteb';
    id: number;
    title: string;
  } | null>(null);
  const [isDeleting, setIsDeleting] = useState<boolean>(false);

  // Toast feedback
  const [successToast, setSuccessToast] = useState<string | null>(null);

  // Load Catalogs & Summary on Mount
  useEffect(() => {
    let isMounted = true;

    // Load summary
    certificateService
      .getSummaryReport()
      .then((data) => {
        if (isMounted) setSummary(data);
      })
      .catch(() => {});

    // Load courses
    courseService
      .getCourses({ per_page: 100 })
      .then((res) => {
        if (isMounted) setCourses(res.data || []);
      })
      .catch(() => {});

    // Load batches
    batchService
      .getBatches({ per_page: 100 })
      .then((res) => {
        if (isMounted) setBatches(res.data || []);
      })
      .catch(() => {});

    // Load sessions
    certificateService
      .getSessions()
      .then((data) => {
        if (isMounted) setSessions(data || []);
      })
      .catch(() => {});

    return () => {
      isMounted = false;
    };
  }, []);

  // Fetch ICBD Certificates
  const fetchIcbd = useCallback(async () => {
    setIcbdLoading(true);
    setIcbdError(null);

    try {
      const filters: Record<string, unknown> = {
        page: icbdPage,
        per_page: 15,
      };

      if (icbdSearch.trim()) filters.search = icbdSearch.trim();
      if (icbdCourseFilter) filters.course_id = Number(icbdCourseFilter);
      if (icbdBatchFilter) filters.batch_id = Number(icbdBatchFilter);
      if (icbdStatusTab !== 'all') filters.is_active = Number(icbdStatusTab);

      const res = await certificateService.getIcbdCertificates(filters);
      setIcbdCerts(res.data || []);
      setIcbdMeta(res.meta || null);
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setIcbdError(apiErr?.response?.data?.message || apiErr?.message || 'Failed to load ICBD certificates.');
    } finally {
      setIcbdLoading(false);
    }
  }, [icbdPage, icbdSearch, icbdCourseFilter, icbdBatchFilter, icbdStatusTab]);

  // Fetch BTEB Certificates
  const fetchBteb = useCallback(async () => {
    setBtebLoading(true);
    setBtebError(null);

    try {
      const filters: Record<string, unknown> = {
        page: btebPage,
        per_page: 15,
      };

      if (btebSearch.trim()) filters.search = btebSearch.trim();
      if (btebSessionFilter) filters.session_id = Number(btebSessionFilter);
      if (btebStatusFilter !== 'all') filters.is_active = Number(btebStatusFilter);

      const res = await certificateService.getBtebCertificates(filters);
      setBtebCerts(res.data || []);
      setBtebMeta(res.meta || null);
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      setBtebError(apiErr?.response?.data?.message || apiErr?.message || 'Failed to load BTEB certificates.');
    } finally {
      setBtebLoading(false);
    }
  }, [btebPage, btebSearch, btebSessionFilter, btebStatusFilter]);

  useEffect(() => {
    if (activeTab === 'icbd') {
      fetchIcbd();
    } else if (activeTab === 'bteb') {
      fetchBteb();
    }
  }, [activeTab, fetchIcbd, fetchBteb]);

  // Handle ICBD deletion
  const executeDelete = async () => {
    if (!deleteConfirm) return;
    setIsDeleting(true);

    try {
      if (deleteConfirm.type === 'icbd') {
        await certificateService.deleteIcbdCertificate(deleteConfirm.id);
        setSuccessToast(`ICBD Certificate #${deleteConfirm.title} was successfully deleted/revoked.`);
        fetchIcbd();
      } else {
        await certificateService.deleteBtebCertificate(deleteConfirm.id);
        setSuccessToast(`BTEB Certificate #${deleteConfirm.title} was successfully deleted.`);
        fetchBteb();
      }
      setDeleteConfirm(null);
    } catch (err: unknown) {
      const apiErr = err as { response?: { data?: { message?: string } }; message?: string };
      alert(apiErr?.response?.data?.message || apiErr?.message || 'Failed to delete certificate.');
    } finally {
      setIsDeleting(false);
    }
  };

  // Helper to open issue modal with student preloaded
  const handleIssueForStudent = (studentId: number) => {
    setPreselectedStudentId(studentId);
    setIsIssueOpen(true);
  };

  return (
    <div className="innova-certificates-page">
      {/* Toast Alert */}
      {successToast && (
        <div style={{ marginBottom: '1rem', position: 'relative' }}>
          <Alert type="success" title="Operation Successful">
            {successToast}
          </Alert>
          <button
            type="button"
            onClick={() => setSuccessToast(null)}
            style={{
              position: 'absolute',
              top: '0.75rem',
              right: '0.75rem',
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              fontSize: '1.25rem',
              lineHeight: 1,
              color: 'var(--innova-gray-500)',
            }}
            aria-label="Dismiss"
          >
            &times;
          </button>
        </div>
      )}

      {/* Page Header */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'flex-start',
          flexWrap: 'wrap',
          gap: '1rem',
          marginBottom: '1.5rem',
        }}
      >
        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.6rem' }}>
            <h1 style={{ fontSize: '1.75rem', fontWeight: 800, margin: 0, color: 'var(--innova-gray-900)' }}>
              Certificates & Verification
            </h1>
            <span className="innova-badge innova-badge-primary">Phase 3.6</span>
          </div>
          <p style={{ margin: '0.35rem 0 0 0', fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
            Manage institutional (ICBD) credentials, technical board (BTEB) certificates, eligibility assessments, and public verification.
          </p>
        </div>

        <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
          <button
            type="button"
            className="innova-btn innova-btn-secondary"
            onClick={() => {
              setBtebModalCert(null);
              setIsBtebModalOpen(true);
            }}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
              <line x1="12" y1="8" x2="12" y2="16" />
              <line x1="8" y1="12" x2="16" y2="12" />
            </svg>
            Register BTEB Record
          </button>

          <button
            type="button"
            className="innova-btn innova-btn-primary"
            onClick={() => {
              setPreselectedStudentId(null);
              setIsIssueOpen(true);
            }}
            style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="8" r="7" />
              <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
            </svg>
            Issue ICBD Certificate
          </button>
        </div>
      </div>

      {/* Metrics Banner */}
      {summary && (
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))',
            gap: '1rem',
            marginBottom: '1.5rem',
          }}
        >
          <div style={{ background: '#fff', border: '1px solid var(--innova-gray-200)', borderRadius: '8px', padding: '1rem' }}>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Total ICBD Issued
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--innova-primary)' }}>
              {summary.icbd_certificates.total}
            </div>
          </div>

          <div style={{ background: '#fff', border: '1px solid var(--innova-gray-200)', borderRadius: '8px', padding: '1rem' }}>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Delivered Credentials
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--innova-success)' }}>
              {summary.icbd_certificates.delivered}
            </div>
          </div>

          <div style={{ background: '#fff', border: '1px solid var(--innova-gray-200)', borderRadius: '8px', padding: '1rem' }}>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Waiting / Processing
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--innova-info)' }}>
              {summary.icbd_certificates.processing}
            </div>
          </div>

          <div style={{ background: '#fff', border: '1px solid var(--innova-gray-200)', borderRadius: '8px', padding: '1rem' }}>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              Printing Queue
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--innova-warning)' }}>
              {summary.icbd_certificates.printing_queue}
            </div>
          </div>

          <div style={{ background: '#fff', border: '1px solid var(--innova-gray-200)', borderRadius: '8px', padding: '1rem' }}>
            <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
              BTEB Board Records
            </div>
            <div style={{ fontSize: '1.6rem', fontWeight: 800, color: 'var(--innova-gray-800)' }}>
              {summary.bteb_certificates.total}
            </div>
          </div>
        </div>
      )}

      {/* Navigation Tabs */}
      <div
        style={{
          display: 'flex',
          gap: '0.5rem',
          borderBottom: '1px solid var(--innova-gray-200)',
          marginBottom: '1.5rem',
          overflowX: 'auto',
        }}
      >
        <button
          type="button"
          onClick={() => setActiveTab('icbd')}
          style={{
            padding: '0.65rem 1.25rem',
            border: 'none',
            background: 'none',
            fontSize: '0.95rem',
            fontWeight: activeTab === 'icbd' ? 700 : 500,
            color: activeTab === 'icbd' ? 'var(--innova-primary)' : 'var(--innova-gray-600)',
            borderBottom: activeTab === 'icbd' ? '2px solid var(--innova-primary)' : '2px solid transparent',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          <span>Institutional (ICBD) Certificates</span>
          {summary && (
            <span style={{ fontSize: '0.75rem', background: 'var(--innova-gray-100)', padding: '0.1rem 0.5rem', borderRadius: '10px' }}>
              {summary.icbd_certificates.total}
            </span>
          )}
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('bteb')}
          style={{
            padding: '0.65rem 1.25rem',
            border: 'none',
            background: 'none',
            fontSize: '0.95rem',
            fontWeight: activeTab === 'bteb' ? 700 : 500,
            color: activeTab === 'bteb' ? 'var(--innova-primary)' : 'var(--innova-gray-600)',
            borderBottom: activeTab === 'bteb' ? '2px solid var(--innova-primary)' : '2px solid transparent',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem',
          }}
        >
          <span>Technical Board (BTEB)</span>
          {summary && (
            <span style={{ fontSize: '0.75rem', background: 'var(--innova-gray-100)', padding: '0.1rem 0.5rem', borderRadius: '10px' }}>
              {summary.bteb_certificates.total}
            </span>
          )}
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('eligibility')}
          style={{
            padding: '0.65rem 1.25rem',
            border: 'none',
            background: 'none',
            fontSize: '0.95rem',
            fontWeight: activeTab === 'eligibility' ? 700 : 500,
            color: activeTab === 'eligibility' ? 'var(--innova-primary)' : 'var(--innova-gray-600)',
            borderBottom: activeTab === 'eligibility' ? '2px solid var(--innova-primary)' : '2px solid transparent',
            cursor: 'pointer',
          }}
        >
          Candidate Eligibility Assessment
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('verify')}
          style={{
            padding: '0.65rem 1.25rem',
            border: 'none',
            background: 'none',
            fontSize: '0.95rem',
            fontWeight: activeTab === 'verify' ? 700 : 500,
            color: activeTab === 'verify' ? 'var(--innova-primary)' : 'var(--innova-gray-600)',
            borderBottom: activeTab === 'verify' ? '2px solid var(--innova-primary)' : '2px solid transparent',
            cursor: 'pointer',
          }}
        >
          Public QR Verifier
        </button>
      </div>

      {/* ========================================================================= */}
      {/* TAB 1: Institutional (ICBD) Certificates                                */}
      {/* ========================================================================= */}
      {activeTab === 'icbd' && (
        <div>
          {/* Filters Bar */}
          <div
            style={{
              background: '#fff',
              border: '1px solid var(--innova-gray-200)',
              borderRadius: '8px',
              padding: '1rem',
              marginBottom: '1rem',
              display: 'flex',
              flexDirection: 'column',
              gap: '1rem',
            }}
          >
            {/* Status Pills */}
            <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
              <button
                type="button"
                className={`innova-btn innova-btn-sm ${icbdStatusTab === 'all' ? 'innova-btn-primary' : 'innova-btn-secondary'}`}
                onClick={() => {
                  setIcbdStatusTab('all');
                  setIcbdPage(1);
                }}
              >
                All Records
              </button>
              {Object.values(ICBD_STATUS_MAP).map((meta) => (
                <button
                  key={meta.code}
                  type="button"
                  className={`innova-btn innova-btn-sm ${icbdStatusTab === String(meta.code) ? 'innova-btn-primary' : 'innova-btn-secondary'}`}
                  onClick={() => {
                    setIcbdStatusTab(String(meta.code) as IcbdStatusTab);
                    setIcbdPage(1);
                  }}
                >
                  {meta.label}
                </button>
              ))}
            </div>

            {/* Search & Selects */}
            <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
              <input
                type="text"
                className="innova-form-control"
                placeholder="Search certificate serial, candidate name, phone, NID..."
                value={icbdSearch}
                onChange={(e) => {
                  setIcbdSearch(e.target.value);
                  setIcbdPage(1);
                }}
                style={{ flex: 1, minWidth: '240px' }}
              />

              <select
                className="innova-form-control"
                value={icbdCourseFilter}
                onChange={(e) => {
                  setIcbdCourseFilter(e.target.value);
                  setIcbdPage(1);
                }}
                style={{ width: '220px' }}
              >
                <option value="">All Programs</option>
                {courses.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>

              <select
                className="innova-form-control"
                value={icbdBatchFilter}
                onChange={(e) => {
                  setIcbdBatchFilter(e.target.value);
                  setIcbdPage(1);
                }}
                style={{ width: '180px' }}
              >
                <option value="">All Batches</option>
                {batches.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.batchnum}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* ICBD Table */}
          {icbdLoading ? (
            <div style={{ textAlign: 'center', padding: '3rem 0' }}>
              <LoadingSpinner size="lg" />
              <div style={{ marginTop: '0.75rem', color: 'var(--innova-gray-500)', fontSize: '0.9rem' }}>
                Loading institutional certificates...
              </div>
            </div>
          ) : icbdError ? (
            <Alert type="error" title="Error Loading Certificates">
              {icbdError}
            </Alert>
          ) : icbdCerts.length === 0 ? (
            <div
              style={{
                background: '#fff',
                padding: '3rem 1rem',
                textAlign: 'center',
                borderRadius: '8px',
                border: '1px dashed var(--innova-gray-300)',
              }}
            >
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--innova-gray-400)" strokeWidth="1.5" style={{ margin: '0 auto 1rem auto' }}>
                <circle cx="12" cy="8" r="7" />
                <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
              </svg>
              <h3 style={{ margin: '0 0 0.5rem 0', fontSize: '1.1rem', color: 'var(--innova-gray-700)' }}>
                No Certificates Found
              </h3>
              <p style={{ margin: '0 0 1rem 0', color: 'var(--innova-gray-500)', fontSize: '0.875rem' }}>
                No institutional certificate records match your selected filter criteria.
              </p>
              <button
                type="button"
                className="innova-btn innova-btn-primary"
                onClick={() => setIsIssueOpen(true)}
              >
                Issue First Certificate
              </button>
            </div>
          ) : (
            <div style={{ background: '#fff', borderRadius: '8px', border: '1px solid var(--innova-gray-200)', overflowX: 'auto' }}>
              <table className="innova-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ background: 'var(--innova-gray-50)', borderBottom: '1px solid var(--innova-gray-200)', textAlign: 'left', fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-600)' }}>
                    <th style={{ padding: '0.75rem 1rem' }}>#</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Candidate</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Certificate No</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Program & Batch</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Curriculum</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Result</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Issue Date</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Status</th>
                    <th style={{ padding: '0.75rem 1rem', textAlign: 'right' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {icbdCerts.map((cert, index) => {
                    const statusMeta = ICBD_STATUS_MAP[cert.is_active] || {
                      label: cert.status_label || 'Unknown',
                      badgeClass: 'innova-badge-gray',
                    };
                    const totalHours =
                      cert.total_hours !== undefined
                        ? cert.total_hours
                        : (cert.theory_hours || 0) + (cert.practical_hours || 0) + (cert.intern_hours || 0);

                    return (
                      <tr
                        key={cert.id}
                        style={{
                          borderBottom: '1px solid var(--innova-gray-200)',
                          fontSize: '0.875rem',
                        }}
                      >
                        <td style={{ padding: '0.75rem 1rem', color: 'var(--innova-gray-500)' }}>
                          {(icbdPage - 1) * 15 + index + 1}
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                            {cert.student?.sname || `Student #${cert.student_id}`}
                          </div>
                          <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                            ID: #{cert.student_id} {cert.student?.phone ? `• ${cert.student.phone}` : ''}
                          </div>
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <button
                            type="button"
                            onClick={() => {
                              setDetailCert(cert);
                              setIsDetailOpen(true);
                            }}
                            style={{
                              background: 'none',
                              border: 'none',
                              padding: 0,
                              fontWeight: 700,
                              color: 'var(--innova-primary)',
                              cursor: 'pointer',
                              textDecoration: 'underline',
                              fontSize: '0.875rem',
                            }}
                          >
                            {cert.certificate_no}
                          </button>
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <div style={{ fontWeight: 500, color: 'var(--innova-gray-800)' }}>
                            {cert.course?.name || `Course #${cert.course_id}`}
                          </div>
                          <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                            {cert.batch?.batchnum || `Batch #${cert.batch_id}`}
                          </div>
                        </td>
                        <td style={{ padding: '0.75rem 1rem', color: 'var(--innova-gray-700)' }}>
                          <strong>{totalHours} hrs</strong>
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <span style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                            {cert.result || 'Pass'}
                          </span>
                        </td>
                        <td style={{ padding: '0.75rem 1rem', color: 'var(--innova-gray-600)' }}>
                          {cert.issue_date ? new Date(cert.issue_date).toLocaleDateString() : '-'}
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <span className={`innova-badge ${statusMeta.badgeClass}`}>
                            {statusMeta.label}
                          </span>
                        </td>
                        <td style={{ padding: '0.75rem 1rem', textAlign: 'right' }}>
                          <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setDetailCert(cert);
                                setIsDetailOpen(true);
                              }}
                              title="View Certificate Details"
                              style={{ padding: '0.25rem 0.5rem' }}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                              </svg>
                            </button>

                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setStatusIcbdCert(cert);
                                setIsStatusIcbdOpen(true);
                              }}
                              title="Transition Lifecycle Status"
                              style={{ padding: '0.25rem 0.5rem' }}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                              </svg>
                            </button>

                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setEditIcbdCert(cert);
                                setIsEditIcbdOpen(true);
                              }}
                              title="Edit Record"
                              style={{ padding: '0.25rem 0.5rem' }}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                              </svg>
                            </button>

                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-danger"
                              onClick={() => {
                                setDeleteConfirm({
                                  type: 'icbd',
                                  id: cert.id,
                                  title: cert.certificate_no,
                                });
                              }}
                              title="Delete Certificate"
                              style={{ padding: '0.25rem 0.5rem' }}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                              </svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>

              {/* Pagination */}
              {icbdMeta && (icbdMeta.last_page ?? 1) > 1 && (
                <div
                  style={{
                    padding: '0.75rem 1rem',
                    borderTop: '1px solid var(--innova-gray-200)',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    fontSize: '0.85rem',
                  }}
                >
                  <span style={{ color: 'var(--innova-gray-500)' }}>
                    Showing page {icbdMeta.current_page} of {icbdMeta.last_page ?? 1} ({icbdMeta.total} records)
                  </span>
                  <div style={{ display: 'flex', gap: '0.35rem' }}>
                    <button
                      type="button"
                      className="innova-btn innova-btn-sm innova-btn-secondary"
                      onClick={() => setIcbdPage((p) => Math.max(1, p - 1))}
                      disabled={icbdPage <= 1}
                    >
                      &larr; Previous
                    </button>
                    <button
                      type="button"
                      className="innova-btn innova-btn-sm innova-btn-secondary"
                      onClick={() => setIcbdPage((p) => Math.min(icbdMeta.last_page ?? 1, p + 1))}
                      disabled={icbdPage >= (icbdMeta.last_page ?? 1)}
                    >
                      Next &rarr;
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: Technical Board (BTEB) Certificates                              */}
      {/* ========================================================================= */}
      {activeTab === 'bteb' && (
        <div>
          {/* Filters Bar */}
          <div
            style={{
              background: '#fff',
              border: '1px solid var(--innova-gray-200)',
              borderRadius: '8px',
              padding: '1rem',
              marginBottom: '1rem',
              display: 'flex',
              gap: '0.75rem',
              flexWrap: 'wrap',
            }}
          >
            <input
              type="text"
              className="innova-form-control"
              placeholder="Search BTEB registration no, certificate no, candidate name..."
              value={btebSearch}
              onChange={(e) => {
                setBtebSearch(e.target.value);
                setBtebPage(1);
              }}
              style={{ flex: 1, minWidth: '240px' }}
            />

            <select
              className="innova-form-control"
              value={btebSessionFilter}
              onChange={(e) => {
                setBtebSessionFilter(e.target.value);
                setBtebPage(1);
              }}
              style={{ width: '220px' }}
            >
              <option value="">All Board Sessions</option>
              {sessions.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.sessionName || s.sessionname || `Session #${s.id}`} {s.year ? `(${s.year})` : ''}
                </option>
              ))}
            </select>

            <select
              className="innova-form-control"
              value={btebStatusFilter}
              onChange={(e) => {
                setBtebStatusFilter(e.target.value);
                setBtebPage(1);
              }}
              style={{ width: '160px' }}
            >
              <option value="all">All Statuses</option>
              <option value="1">Active / Delivered</option>
              <option value="0">Inactive / Revoked</option>
            </select>
          </div>

          {/* BTEB Table */}
          {btebLoading ? (
            <div style={{ textAlign: 'center', padding: '3rem 0' }}>
              <LoadingSpinner size="lg" />
              <div style={{ marginTop: '0.75rem', color: 'var(--innova-gray-500)', fontSize: '0.9rem' }}>
                Loading BTEB technical board certificates...
              </div>
            </div>
          ) : btebError ? (
            <Alert type="error" title="Error Loading BTEB Records">
              {btebError}
            </Alert>
          ) : btebCerts.length === 0 ? (
            <div
              style={{
                background: '#fff',
                padding: '3rem 1rem',
                textAlign: 'center',
                borderRadius: '8px',
                border: '1px dashed var(--innova-gray-300)',
              }}
            >
              <h3 style={{ margin: '0 0 0.5rem 0', fontSize: '1.1rem', color: 'var(--innova-gray-700)' }}>
                No BTEB Certificates Found
              </h3>
              <p style={{ margin: '0 0 1rem 0', color: 'var(--innova-gray-500)', fontSize: '0.875rem' }}>
                No technical board records match the active filter query.
              </p>
              <button
                type="button"
                className="innova-btn innova-btn-primary"
                onClick={() => {
                  setBtebModalCert(null);
                  setIsBtebModalOpen(true);
                }}
              >
                Register BTEB Record
              </button>
            </div>
          ) : (
            <div style={{ background: '#fff', borderRadius: '8px', border: '1px solid var(--innova-gray-200)', overflowX: 'auto' }}>
              <table className="innova-table" style={{ width: '100%', borderCollapse: 'collapse' }}>
                <thead>
                  <tr style={{ background: 'var(--innova-gray-50)', borderBottom: '1px solid var(--innova-gray-200)', textAlign: 'left', fontSize: '0.75rem', textTransform: 'uppercase', color: 'var(--innova-gray-600)' }}>
                    <th style={{ padding: '0.75rem 1rem' }}>#</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Candidate</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Board Session</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Reg No</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Certificate No (cno)</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Result</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Passing Date</th>
                    <th style={{ padding: '0.75rem 1rem' }}>Status</th>
                    <th style={{ padding: '0.75rem 1rem', textAlign: 'right' }}>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {btebCerts.map((cert, index) => {
                    const statusMeta = BTEB_STATUS_MAP[cert.IsActive] || {
                      label: cert.IsActive === 1 ? 'Active' : 'Inactive',
                      badgeClass: cert.IsActive === 1 ? 'innova-badge-success' : 'innova-badge-error',
                    };

                    return (
                      <tr key={cert.id} style={{ borderBottom: '1px solid var(--innova-gray-200)', fontSize: '0.875rem' }}>
                        <td style={{ padding: '0.75rem 1rem', color: 'var(--innova-gray-500)' }}>
                          {(btebPage - 1) * 15 + index + 1}
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                            {cert.StudentsInfo?.sname || cert.student?.sname || `Student #${cert.student_id}`}
                          </div>
                          <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                            ID: #{cert.student_id}
                          </div>
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          {cert.StudentsSessions?.sessionName || cert.StudentsSessions?.sessionname || `Session #${cert.session_id}`}
                        </td>
                        <td style={{ padding: '0.75rem 1rem', fontWeight: 600, color: 'var(--innova-primary)' }}>
                          {cert.regNo}
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          {cert.cno || <span style={{ color: 'var(--innova-gray-400)' }}>None</span>}
                        </td>
                        <td style={{ padding: '0.75rem 1rem', fontWeight: 600 }}>
                          {cert.Result || '-'}
                        </td>
                        <td style={{ padding: '0.75rem 1rem', color: 'var(--innova-gray-600)' }}>
                          {cert.pDate ? new Date(cert.pDate).toLocaleDateString() : '-'}
                        </td>
                        <td style={{ padding: '0.75rem 1rem' }}>
                          <span className={`innova-badge ${statusMeta.badgeClass}`}>
                            {statusMeta.label}
                          </span>
                        </td>
                        <td style={{ padding: '0.75rem 1rem', textAlign: 'right' }}>
                          <div style={{ display: 'inline-flex', gap: '0.35rem' }}>
                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-secondary"
                              onClick={() => {
                                setBtebModalCert(cert);
                                setIsBtebModalOpen(true);
                              }}
                              title="Edit BTEB Record"
                              style={{ padding: '0.25rem 0.5rem' }}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                              </svg>
                            </button>

                            <button
                              type="button"
                              className="innova-btn innova-btn-sm innova-btn-danger"
                              onClick={() => {
                                setDeleteConfirm({
                                  type: 'bteb',
                                  id: cert.id,
                                  title: cert.regNo,
                                });
                              }}
                              title="Delete Record"
                              style={{ padding: '0.25rem 0.5rem' }}
                            >
                              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                              </svg>
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>

              {/* BTEB Pagination */}
              {btebMeta && (btebMeta.last_page ?? 1) > 1 && (
                <div
                  style={{
                    padding: '0.75rem 1rem',
                    borderTop: '1px solid var(--innova-gray-200)',
                    display: 'flex',
                    justifyContent: 'space-between',
                    alignItems: 'center',
                    fontSize: '0.85rem',
                  }}
                >
                  <span style={{ color: 'var(--innova-gray-500)' }}>
                    Showing page {btebMeta.current_page} of {btebMeta.last_page ?? 1} ({btebMeta.total} records)
                  </span>
                  <div style={{ display: 'flex', gap: '0.35rem' }}>
                    <button
                      type="button"
                      className="innova-btn innova-btn-sm innova-btn-secondary"
                      onClick={() => setBtebPage((p) => Math.max(1, p - 1))}
                      disabled={btebPage <= 1}
                    >
                      &larr; Previous
                    </button>
                    <button
                      type="button"
                      className="innova-btn innova-btn-sm innova-btn-secondary"
                      onClick={() => setBtebPage((p) => Math.min(btebMeta.last_page ?? 1, p + 1))}
                      disabled={btebPage >= (btebMeta.last_page ?? 1)}
                    >
                      Next &rarr;
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 3: Candidate Eligibility Assessment                                 */}
      {/* ========================================================================= */}
      {activeTab === 'eligibility' && (
        <EligibilityCheckerCard onIssueForStudent={handleIssueForStudent} />
      )}

      {/* ========================================================================= */}
      {/* TAB 4: Public QR Verifier Preview                                       */}
      {/* ========================================================================= */}
      {activeTab === 'verify' && <PublicCertificateVerifier />}

      {/* ========================================================================= */}
      {/* MODALS                                                                   */}
      {/* ========================================================================= */}

      {/* Detail Modal */}
      <CertificateDetailModal
        certificate={detailCert}
        isOpen={isDetailOpen}
        onClose={() => {
          setIsDetailOpen(false);
          setDetailCert(null);
        }}
        onEdit={(cert) => {
          setIsDetailOpen(false);
          setEditIcbdCert(cert);
          setIsEditIcbdOpen(true);
        }}
        onUpdateStatus={(cert) => {
          setIsDetailOpen(false);
          setStatusIcbdCert(cert);
          setIsStatusIcbdOpen(true);
        }}
      />

      {/* Issue Modal */}
      <IssueCertificateModal
        isOpen={isIssueOpen}
        preselectedStudentId={preselectedStudentId}
        onClose={() => {
          setIsIssueOpen(false);
          setPreselectedStudentId(null);
        }}
        onSuccess={(created) => {
          setSuccessToast(`Certificate ${created.certificate_no} issued successfully.`);
          fetchIcbd();
          certificateService.getSummaryReport().then(setSummary).catch(() => {});
        }}
      />

      {/* Edit ICBD Modal */}
      <CertificateFormModal
        certificate={editIcbdCert}
        isOpen={isEditIcbdOpen}
        onClose={() => {
          setIsEditIcbdOpen(false);
          setEditIcbdCert(null);
        }}
        onSuccess={(updated) => {
          setSuccessToast(`Certificate ${updated.certificate_no} updated successfully.`);
          fetchIcbd();
        }}
      />

      {/* Status Modal */}
      <CertificateStatusModal
        certificate={statusIcbdCert}
        isOpen={isStatusIcbdOpen}
        onClose={() => {
          setIsStatusIcbdOpen(false);
          setStatusIcbdCert(null);
        }}
        onSuccess={(updated) => {
          setSuccessToast(`Certificate ${updated.certificate_no} status changed.`);
          fetchIcbd();
          certificateService.getSummaryReport().then(setSummary).catch(() => {});
        }}
      />

      {/* BTEB Modal */}
      <BtebCertificateModal
        certificate={btebModalCert}
        isOpen={isBtebModalOpen}
        onClose={() => {
          setIsBtebModalOpen(false);
          setBtebModalCert(null);
        }}
        onSuccess={(saved) => {
          setSuccessToast(`BTEB Certificate #${saved.regNo} saved successfully.`);
          fetchBteb();
          certificateService.getSummaryReport().then(setSummary).catch(() => {});
        }}
      />

      {/* Delete Confirmation Modal */}
      {deleteConfirm && (
        <div className="innova-modal-backdrop" role="dialog" aria-modal="true">
          <div className="innova-modal" style={{ maxWidth: '480px' }}>
            <div className="innova-modal-header">
              <h2 className="innova-modal-title">Confirm Deletion</h2>
              <button
                type="button"
                className="innova-btn innova-btn-sm innova-btn-secondary"
                onClick={() => setDeleteConfirm(null)}
              >
                &times;
              </button>
            </div>
            <div className="innova-modal-body">
              <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-700)' }}>
                Are you sure you want to delete certificate <strong>{deleteConfirm.title}</strong>?
                {deleteConfirm.type === 'icbd' && (
                  <span style={{ display: 'block', marginTop: '0.5rem', color: 'var(--innova-error)', fontSize: '0.8rem' }}>
                    This will soft-delete the certificate record and revert the candidate's registration status if no other active certificate exists.
                  </span>
                )}
              </p>
            </div>
            <div className="innova-modal-footer">
              <button
                type="button"
                className="innova-btn innova-btn-secondary"
                onClick={() => setDeleteConfirm(null)}
                disabled={isDeleting}
              >
                Cancel
              </button>
              <button
                type="button"
                className="innova-btn innova-btn-danger"
                onClick={executeDelete}
                disabled={isDeleting}
              >
                {isDeleting ? 'Deleting...' : 'Delete Record'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

import React, { useState } from 'react';
import { CertificateEligibility } from '../../types';
import { certificateService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

interface EligibilityCheckerCardProps {
  onIssueForStudent?: (studentId: number) => void;
}

export const EligibilityCheckerCard: React.FC<EligibilityCheckerCardProps> = ({
  onIssueForStudent,
}) => {
  const [studentIdInput, setStudentIdInput] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(false);
  const [result, setResult] = useState<CertificateEligibility | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleCheck = async (e: React.FormEvent) => {
    e.preventDefault();
    const id = studentIdInput.trim();
    if (!id) return;

    setLoading(true);
    setError(null);
    setResult(null);

    try {
      const data = await certificateService.checkEligibility(id);
      setResult(data);
    } catch (err: unknown) {
      const apiErr = err as {
        response?: { status?: number; data?: { message?: string } };
        message?: string;
      };
      if (apiErr?.response?.status === 404) {
        setError(`Student record with ID #${id} was not found.`);
      } else {
        setError(
          apiErr?.response?.data?.message || apiErr?.message || 'Failed to evaluate student eligibility.'
        );
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div
      style={{
        background: '#fff',
        border: '1px solid var(--innova-gray-200)',
        borderRadius: '8px',
        padding: '1.25rem',
      }}
    >
      <div style={{ marginBottom: '1rem' }}>
        <h3 style={{ margin: '0 0 0.35rem 0', fontSize: '1.1rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
          Student Certificate Eligibility Assessment
        </h3>
        <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
          Evaluate enrollment status, financial clearance, and active certificates before issuing credentials.
        </p>
      </div>

      <form onSubmit={handleCheck} style={{ display: 'flex', gap: '0.75rem', marginBottom: '1.25rem' }}>
        <input
          type="text"
          className="innova-form-control"
          placeholder="Enter Student ID (e.g. 101)..."
          value={studentIdInput}
          onChange={(e) => setStudentIdInput(e.target.value)}
          style={{ maxWidth: '280px' }}
          required
        />
        <button
          type="submit"
          className="innova-btn innova-btn-primary"
          disabled={loading || !studentIdInput.trim()}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem' }}
        >
          {loading ? (
            <>
              <LoadingSpinner size="sm" />
              <span>Evaluating...</span>
            </>
          ) : (
            <>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8" />
                <line x1="21" y1="21" x2="16.65" y2="16.65" />
              </svg>
              <span>Check Eligibility</span>
            </>
          )}
        </button>
      </form>

      {error && (
        <Alert type="error" title="Assessment Failed">
          {error}
        </Alert>
      )}

      {result && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem' }}>
          {/* Main Status Verdict */}
          <div
            style={{
              padding: '1rem',
              borderRadius: '8px',
              border: result.eligible ? '1px solid rgba(16, 185, 129, 0.4)' : '1px solid rgba(239, 68, 68, 0.4)',
              background: result.eligible ? 'rgba(16, 185, 129, 0.05)' : 'rgba(239, 68, 68, 0.05)',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              flexWrap: 'wrap',
              gap: '1rem',
            }}
          >
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.25rem' }}>
                <span
                  style={{
                    display: 'inline-block',
                    width: '10px',
                    height: '10px',
                    borderRadius: '50%',
                    background: result.eligible ? '#10b981' : '#ef4444',
                  }}
                />
                <span style={{ fontSize: '1.05rem', fontWeight: 700, color: result.eligible ? '#065f46' : '#991b1b' }}>
                  {result.eligible ? 'Candidate Eligible for Certification' : 'Candidate Ineligible'}
                </span>
              </div>
              <div style={{ fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
                Candidate: <strong>{result.student.name}</strong> (ID #{result.student.id}){' '}
                {result.student.phone && `• ${result.student.phone}`}
              </div>
            </div>

            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <span className={`innova-badge ${result.can_issue_icbd ? 'innova-badge-success' : 'innova-badge-gray'}`}>
                ICBD: {result.can_issue_icbd ? 'Available' : 'Blocked'}
              </span>
              <span className={`innova-badge ${result.can_issue_bteb ? 'innova-badge-success' : 'innova-badge-gray'}`}>
                BTEB: {result.can_issue_bteb ? 'Available' : 'Blocked'}
              </span>
            </div>
          </div>

          {/* Academic & Financial Status Grid */}
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
              gap: '1rem',
            }}
          >
            <div
              style={{
                background: 'var(--innova-gray-50)',
                padding: '0.85rem',
                borderRadius: '6px',
                border: '1px solid var(--innova-gray-200)',
              }}
            >
              <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Enrolled Program
              </div>
              <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)', marginTop: '0.2rem' }}>
                {result.student.course?.name || 'No Course Linked'}
              </div>
              <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                Batch: {result.student.batch?.batchnum || 'No Batch'}
              </div>
            </div>

            <div
              style={{
                background: 'var(--innova-gray-50)',
                padding: '0.85rem',
                borderRadius: '6px',
                border: '1px solid var(--innova-gray-200)',
              }}
            >
              <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Financial Clearance
              </div>
              <div
                style={{
                  fontWeight: 700,
                  fontSize: '1rem',
                  color: result.has_outstanding_dues ? 'var(--innova-error)' : 'var(--innova-success)',
                  marginTop: '0.2rem',
                }}
              >
                ৳{result.student.balance}
              </div>
              <div style={{ fontSize: '0.8rem', color: 'var(--innova-gray-600)' }}>
                {result.has_outstanding_dues ? 'Outstanding Dues Warning' : 'Fully Paid / Zero Dues'}
              </div>
            </div>

            <div
              style={{
                background: 'var(--innova-gray-50)',
                padding: '0.85rem',
                borderRadius: '6px',
                border: '1px solid var(--innova-gray-200)',
              }}
            >
              <div style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', textTransform: 'uppercase' }}>
                Existing Certificates
              </div>
              <div style={{ fontSize: '0.85rem', marginTop: '0.2rem' }}>
                {result.existing_icbd_certificate ? (
                  <span style={{ color: 'var(--innova-primary)', fontWeight: 600 }}>
                    ICBD: {result.existing_icbd_certificate.certificate_no} ({result.existing_icbd_certificate.status})
                  </span>
                ) : (
                  <span style={{ color: 'var(--innova-gray-500)' }}>No ICBD certificate</span>
                )}
              </div>
              <div style={{ fontSize: '0.85rem', marginTop: '0.1rem' }}>
                {result.existing_bteb_certificate ? (
                  <span style={{ color: 'var(--innova-primary)', fontWeight: 600 }}>
                    BTEB: {result.existing_bteb_certificate.cno || result.existing_bteb_certificate.regNo}
                  </span>
                ) : (
                  <span style={{ color: 'var(--innova-gray-500)' }}>No BTEB certificate</span>
                )}
              </div>
            </div>
          </div>

          {/* Reasons List */}
          {result.reasons && result.reasons.length > 0 && (
            <div
              style={{
                padding: '0.85rem',
                borderRadius: '6px',
                background: 'var(--innova-gray-50)',
                border: '1px solid var(--innova-gray-200)',
                fontSize: '0.85rem',
              }}
            >
              <div style={{ fontWeight: 600, color: 'var(--innova-gray-800)', marginBottom: '0.4rem' }}>
                Eligibility Assessment Notes:
              </div>
              <ul style={{ margin: 0, paddingLeft: '1.25rem', display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
                {result.reasons.map((reason, i) => (
                  <li key={i} style={{ color: 'var(--innova-gray-700)' }}>
                    {reason}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {/* Action Button */}
          {result.can_issue_icbd && onIssueForStudent && (
            <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '0.5rem' }}>
              <button
                type="button"
                className="innova-btn innova-btn-primary"
                onClick={() => onIssueForStudent(result.student.id)}
              >
                Proceed to Issue ICBD Certificate &rarr;
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

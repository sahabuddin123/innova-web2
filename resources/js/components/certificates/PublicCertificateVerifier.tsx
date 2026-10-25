import React, { useState } from 'react';
import { PublicVerificationResult } from '../../types';
import { certificateService } from '../../services';
import { Alert, LoadingSpinner } from '../common';

export const PublicCertificateVerifier: React.FC = () => {
  const [certInput, setCertInput] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(false);
  const [result, setResult] = useState<PublicVerificationResult | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleVerify = async (e: React.FormEvent) => {
    e.preventDefault();
    const id = certInput.trim();
    if (!id) return;

    setLoading(true);
    setError(null);
    setResult(null);

    try {
      const data = await certificateService.verifyPublicCertificate(id);
      setResult(data);
    } catch (err: unknown) {
      const apiErr = err as {
        response?: { status?: number; data?: { message?: string } };
        message?: string;
      };
      if (apiErr?.response?.status === 404) {
        setError(`No authentic certificate found matching "${id}". Please verify the serial number.`);
      } else {
        setError(
          apiErr?.response?.data?.message || apiErr?.message || 'Certificate verification failed.'
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
        padding: '1.5rem',
      }}
    >
      <div style={{ marginBottom: '1.25rem', textAlign: 'center' }}>
        <div
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: '44px',
            height: '44px',
            borderRadius: '50%',
            background: 'var(--innova-primary-light, #e0e7ff)',
            color: 'var(--innova-primary)',
            marginBottom: '0.5rem',
          }}
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          </svg>
        </div>
        <h3 style={{ margin: '0 0 0.35rem 0', fontSize: '1.25rem', fontWeight: 700, color: 'var(--innova-gray-900)' }}>
          Public Certificate Authenticity Verification
        </h3>
        <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-500)', maxWidth: '520px', marginLeft: 'auto', marginRight: 'auto' }}>
          Authenticate institutional credentials issued by INNOVA Computers BD or Bangladesh Technical Education Board (BTEB). Enforces strict data privacy.
        </p>
      </div>

      <form
        onSubmit={handleVerify}
        style={{
          display: 'flex',
          gap: '0.75rem',
          maxWidth: '540px',
          margin: '0 auto 1.5rem auto',
        }}
      >
        <input
          type="text"
          className="innova-form-control"
          placeholder="Enter Certificate Serial (e.g. ICBD0001, BTEB Reg No)..."
          value={certInput}
          onChange={(e) => setCertInput(e.target.value)}
          required
          style={{ fontSize: '0.95rem' }}
        />
        <button
          type="submit"
          className="innova-btn innova-btn-primary"
          disabled={loading || !certInput.trim()}
          style={{ display: 'inline-flex', alignItems: 'center', gap: '0.4rem', whiteSpace: 'nowrap' }}
        >
          {loading ? (
            <>
              <LoadingSpinner size="sm" />
              <span>Verifying...</span>
            </>
          ) : (
            <>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              <span>Verify Now</span>
            </>
          )}
        </button>
      </form>

      {error && (
        <div style={{ maxWidth: '640px', margin: '0 auto' }}>
          <Alert type="error" title="Verification Notice">
            {error}
          </Alert>
        </div>
      )}

      {result && (
        <div
          style={{
            maxWidth: '640px',
            margin: '0 auto',
            border: result.is_valid ? '2px solid #10b981' : '2px solid #ef4444',
            borderRadius: '10px',
            background: result.is_valid ? '#f0fdf4' : '#fef2f2',
            padding: '1.5rem',
            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)',
          }}
        >
          {/* Header Status */}
          <div
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              borderBottom: '1px solid rgba(0, 0, 0, 0.08)',
              paddingBottom: '0.75rem',
              marginBottom: '1rem',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
              <div
                style={{
                  width: '28px',
                  height: '28px',
                  borderRadius: '50%',
                  background: result.is_valid ? '#10b981' : '#ef4444',
                  color: '#fff',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                }}
              >
                {result.is_valid ? (
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                ) : (
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                  </svg>
                )}
              </div>
              <span style={{ fontSize: '1.1rem', fontWeight: 800, color: result.is_valid ? '#065f46' : '#991b1b' }}>
                {result.is_valid ? 'Authentic Credential Verified' : 'Credential Revoked / Inactive'}
              </span>
            </div>
            <span className={`innova-badge ${result.is_valid ? 'innova-badge-success' : 'innova-badge-error'}`}>
              {result.status}
            </span>
          </div>

          {/* Details Table */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '0.75rem', fontSize: '0.85rem' }}>
            <div>
              <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase' }}>Candidate Name</div>
              <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--innova-gray-900)' }}>
                {result.student_name}
              </div>
            </div>

            <div>
              <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase' }}>Certificate Type</div>
              <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>{result.certificate_type}</div>
            </div>

            <div>
              <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase' }}>Certificate Number</div>
              <div style={{ fontWeight: 700, color: 'var(--innova-primary)' }}>
                {result.certificate_no || result.registration_no}
              </div>
            </div>

            {result.course_name && (
              <div>
                <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase' }}>Course / Program</div>
                <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>{result.course_name}</div>
              </div>
            )}

            {result.session && (
              <div>
                <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase' }}>Board Session</div>
                <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)' }}>{result.session}</div>
              </div>
            )}

            {result.result && (
              <div>
                <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase' }}>Result / Grade</div>
                <div style={{ fontWeight: 700, color: 'var(--innova-gray-900)' }}>{result.result}</div>
              </div>
            )}

            {result.curriculum_hours && (
              <div style={{ gridColumn: 'span 2', background: 'rgba(255, 255, 255, 0.7)', padding: '0.65rem', borderRadius: '6px' }}>
                <div style={{ color: 'var(--innova-gray-500)', fontSize: '0.75rem', textTransform: 'uppercase', marginBottom: '0.25rem' }}>
                  Curriculum Breakdown
                </div>
                <div style={{ display: 'flex', gap: '1rem', fontSize: '0.8rem' }}>
                  <span>Theory: <strong>{result.curriculum_hours.classroom_theory}h</strong></span>
                  <span>Practical: <strong>{result.curriculum_hours.practical_lab}h</strong></span>
                  <span>Internship: <strong>{result.curriculum_hours.internship}h</strong></span>
                  <span style={{ color: 'var(--innova-primary)', fontWeight: 700 }}>
                    Total: {result.curriculum_hours.total_course_hours}h
                  </span>
                </div>
              </div>
            )}
          </div>

          {/* Footer Institution */}
          <div
            style={{
              marginTop: '1rem',
              paddingTop: '0.75rem',
              borderTop: '1px solid rgba(0, 0, 0, 0.08)',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              fontSize: '0.75rem',
              color: 'var(--innova-gray-500)',
            }}
          >
            <span>{result.institution.name}</span>
            <span>Verified: {new Date(result.verified_at).toLocaleDateString()}</span>
          </div>
        </div>
      )}
    </div>
  );
};

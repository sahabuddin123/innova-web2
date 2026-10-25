import React, { useState } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { AuthRoleType } from '../../types/auth';
import { Card, Alert, LoadingSpinner } from '../common';
import { RequireRole, RequirePermission } from './AuthBoundary';

/**
 * INNOVA WEB — Isolated Authentication Demonstration Mount (Phase 3.1)
 *
 * Provides an isolated demonstration of:
 * - Multi-guard authentication (Admin vs Student)
 * - Safe state management (loading, authenticated, unauthenticated, error)
 * - Profile hydration from existing /me endpoints
 * - Role & Spatie permission boundary checks
 * - Token revocation on logout
 *
 * NOTE: This is mounted ONLY on the /react-health test route.
 * It does NOT replace any production Blade login or dashboard views.
 */
export const AuthDemo: React.FC = () => {
  const {
    user,
    roleType,
    isAuthenticated,
    isLoading,
    error,
    login,
    logout,
    checkAuth,
    clearError,
  } = useAuth();

  const [selectedRole, setSelectedRole] = useState<AuthRoleType>('admin');
  const [email, setEmail] = useState<string>('');
  const [password, setPassword] = useState<string>('');
  const [submitting, setSubmitting] = useState<boolean>(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email || !password) {
      return;
    }

    setSubmitting(true);
    try {
      await login({ email, password }, selectedRole);
    } finally {
      setSubmitting(false);
      // Clear password field immediately for security
      setPassword('');
    }
  };

  const handleLogout = async () => {
    setSubmitting(true);
    try {
      await logout();
    } finally {
      setSubmitting(false);
    }
  };

  const handleRefreshProfile = async () => {
    setSubmitting(true);
    try {
      await checkAuth();
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Card
      title="React Authentication & Access Foundation"
      subtitle="Phase 3.1: Multi-Guard Sanctum Authentication State & Access Gates"
      footer={
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}>
          <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
            Status:{' '}
            <strong>
              {isAuthenticated
                ? `Authenticated (${roleType === 'admin' || user?.is_admin ? 'Admin' : 'Student'})`
                : 'Unauthenticated'}
            </strong>
          </span>
          {isAuthenticated && (
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <button
                type="button"
                className="innova-btn innova-btn-outline"
                onClick={handleRefreshProfile}
                disabled={submitting || isLoading}
              >
                {submitting ? <LoadingSpinner size="sm" label="Validating..." /> : 'Verify Session (/me)'}
              </button>
              <button
                type="button"
                className="innova-btn innova-btn-danger"
                onClick={handleLogout}
                disabled={submitting || isLoading}
              >
                Logout
              </button>
            </div>
          )}
        </div>
      }
    >
      {error && (
        <div style={{ marginBottom: '1rem' }}>
          <Alert type="error" title="Authentication Notice">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <span>{error}</span>
              <button
                type="button"
                onClick={clearError}
                style={{
                  background: 'none',
                  border: 'none',
                  color: 'inherit',
                  cursor: 'pointer',
                  fontWeight: 600,
                  fontSize: '0.8rem',
                }}
              >
                Dismiss
              </button>
            </div>
          </Alert>
        </div>
      )}

      {isLoading && !submitting ? (
        <div style={{ display: 'flex', justifyContent: 'center', padding: '2rem' }}>
          <LoadingSpinner size="md" label="Hydrating authentication state..." />
        </div>
      ) : isAuthenticated && user ? (
        <div>
          <Alert type="success" title="Session Active">
            Connected via Laravel Sanctum token. All credentials and session tokens are held in-memory and tab session storage without persisting to permanent localStorage.
          </Alert>

          <div className="innova-grid" style={{ marginTop: '1.25rem' }}>
            <div className="innova-stat-box">
              <div className="innova-stat-label">User Name</div>
              <div className="innova-stat-value" style={{ fontSize: '1.15rem' }}>
                {user.name}
              </div>
              <span style={{ fontSize: '0.8rem', color: 'var(--innova-gray-500)' }}>
                ID: #{user.id}
              </span>
            </div>

            <div className="innova-stat-box">
              <div className="innova-stat-label">Email Address</div>
              <div className="innova-stat-value" style={{ fontSize: '1.15rem', wordBreak: 'break-all' }}>
                {user.email}
              </div>
              <span
                className={`innova-badge ${user.is_admin ? 'innova-badge-primary' : 'innova-badge-success'}`}
                style={{ marginTop: '0.25rem' }}
              >
                {user.is_admin ? 'Admin Guard' : 'Student Guard'}
              </span>
            </div>

            <div className="innova-stat-box">
              <div className="innova-stat-label">Assigned Roles</div>
              <div style={{ marginTop: '0.5rem', display: 'flex', flexWrap: 'wrap', gap: '0.25rem' }}>
                {user.roles && user.roles.length > 0 ? (
                  user.roles.map((r) => (
                    <span key={r} className="innova-badge innova-badge-primary">
                      {r}
                    </span>
                  ))
                ) : (
                  <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
                    None (Standard User)
                  </span>
                )}
              </div>
            </div>

            <div className="innova-stat-box">
              <div className="innova-stat-label">Spatie Permissions</div>
              <div style={{ fontSize: '1.15rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                {user.permissions ? `${user.permissions.length} Granted` : 'N/A'}
              </div>
              {user.permissions && user.permissions.length > 0 && (
                <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)' }}>
                  {user.permissions.slice(0, 3).join(', ')}
                  {user.permissions.length > 3 ? '...' : ''}
                </span>
              )}
            </div>
          </div>

          <div style={{ marginTop: '1.5rem', padding: '1rem', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
            <h4 style={{ margin: '0 0 0.5rem 0', fontSize: '0.9rem', fontWeight: 600 }}>
              Authorization Boundary Verification:
            </h4>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
              <RequireRole
                role="super-admin"
                fallback={
                  <div style={{ fontSize: '0.85rem', color: '#64748b' }}>
                    RequireRole('super-admin'): Gated (Current user lacks this role)
                  </div>
                }
              >
                <div style={{ fontSize: '0.85rem', color: '#16a34a', fontWeight: 600 }}>
                  RequireRole('super-admin'): Authorized (Role verified)
                </div>
              </RequireRole>

              <RequirePermission
                permission="manage-courses"
                fallback={
                  <div style={{ fontSize: '0.85rem', color: '#64748b' }}>
                    RequirePermission('manage-courses'): Gated (Permission not held)
                  </div>
                }
              >
                <div style={{ fontSize: '0.85rem', color: '#16a34a', fontWeight: 600 }}>
                  RequirePermission('manage-courses'): Authorized (Permission granted)
                </div>
              </RequirePermission>
            </div>
          </div>
        </div>
      ) : (
        <div>
          <p style={{ margin: '0 0 1rem 0', fontSize: '0.9rem', color: 'var(--innova-gray-600)' }}>
            This mount connects directly to the existing Laravel 8 authentication APIs. Test multi-guard authentication below:
          </p>

          <form onSubmit={handleSubmit}>
            <div style={{ marginBottom: '1rem' }}>
              <label style={{ display: 'block', fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.5rem', color: 'var(--innova-gray-700)' }}>
                Target Guard & Endpoint:
              </label>
              <div style={{ display: 'flex', gap: '1.5rem' }}>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.9rem', cursor: 'pointer' }}>
                  <input
                    type="radio"
                    name="roleType"
                    value="admin"
                    checked={selectedRole === 'admin'}
                    onChange={() => setSelectedRole('admin')}
                    disabled={submitting}
                  />
                  <strong>Admin Guard</strong> (<code>/api/v1/admin/auth/login</code>)
                </label>
                <label style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', fontSize: '0.9rem', cursor: 'pointer' }}>
                  <input
                    type="radio"
                    name="roleType"
                    value="user"
                    checked={selectedRole === 'user'}
                    onChange={() => setSelectedRole('user')}
                    disabled={submitting}
                  />
                  <strong>Student Guard</strong> (<code>/api/v1/auth/login</code>)
                </label>
              </div>
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem', marginBottom: '1rem' }}>
              <div>
                <label
                  htmlFor="auth-email-input"
                  style={{ display: 'block', fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.25rem', color: 'var(--innova-gray-700)' }}
                >
                  Email Address
                </label>
                <input
                  id="auth-email-input"
                  type="email"
                  className="innova-input"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder={selectedRole === 'admin' ? 'admin@example.com' : 'student@example.com'}
                  required
                  disabled={submitting}
                  style={{
                    width: '100%',
                    padding: '0.5rem 0.75rem',
                    border: '1px solid #cbd5e1',
                    borderRadius: '6px',
                    fontSize: '0.9rem',
                  }}
                />
              </div>

              <div>
                <label
                  htmlFor="auth-password-input"
                  style={{ display: 'block', fontSize: '0.85rem', fontWeight: 600, marginBottom: '0.25rem', color: 'var(--innova-gray-700)' }}
                >
                  Password
                </label>
                <input
                  id="auth-password-input"
                  type="password"
                  className="innova-input"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  required
                  disabled={submitting}
                  style={{
                    width: '100%',
                    padding: '0.5rem 0.75rem',
                    border: '1px solid #cbd5e1',
                    borderRadius: '6px',
                    fontSize: '0.9rem',
                  }}
                />
              </div>
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
              <button
                type="submit"
                className="innova-btn innova-btn-primary"
                disabled={submitting || !email || !password}
              >
                {submitting ? (
                  <LoadingSpinner size="sm" label="Authenticating..." />
                ) : (
                  `Log In as ${selectedRole === 'admin' ? 'Administrator' : 'Student'}`
                )}
              </button>
            </div>
          </form>
        </div>
      )}
    </Card>
  );
};

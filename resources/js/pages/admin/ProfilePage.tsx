import React, { useState } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { Card, Alert, LoadingSpinner } from '../../components/common';

export const ProfilePage: React.FC = () => {
  const { user, checkAuth } = useAuth();
  const [refreshing, setRefreshing] = useState(false);
  const [successNotice, setSuccessNotice] = useState<string | null>(null);

  const handleRefresh = async () => {
    setRefreshing(true);
    setSuccessNotice(null);
    try {
      await checkAuth();
      setSuccessNotice('Administrator profile refreshed successfully from /api/v1/admin/auth/me.');
    } finally {
      setRefreshing(false);
    }
  };

  return (
    <div>
      <div style={{ marginBottom: '1.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <div>
          <h1 style={{ fontSize: '1.75rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-900)' }}>
            Administrator Profile
          </h1>
          <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
            Identity, Spatie RBAC assignments, and Sanctum session metadata.
          </p>
        </div>

        <button
          type="button"
          className="innova-btn innova-btn-outline"
          onClick={handleRefresh}
          disabled={refreshing}
        >
          {refreshing ? <LoadingSpinner size="sm" label="Revalidating..." /> : 'Revalidate Session (/me)'}
        </button>
      </div>

      {successNotice && (
        <div style={{ marginBottom: '1rem' }}>
          <Alert type="success" title="Profile Synchronized">
            {successNotice}
          </Alert>
        </div>
      )}

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: '1.5rem' }}>
        {/* User Identity Details */}
        <Card title="Administrative Identity" subtitle="Core account credentials and system identity">
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.9rem' }}>
            <tbody>
              <tr style={{ borderBottom: '1px solid var(--innova-gray-100)' }}>
                <td style={{ padding: '0.75rem 0', fontWeight: 600, color: 'var(--innova-gray-600)', width: '35%' }}>
                  Admin ID
                </td>
                <td style={{ padding: '0.75rem 0', color: 'var(--innova-gray-900)' }}>
                  #{user?.id}
                </td>
              </tr>
              <tr style={{ borderBottom: '1px solid var(--innova-gray-100)' }}>
                <td style={{ padding: '0.75rem 0', fontWeight: 600, color: 'var(--innova-gray-600)' }}>
                  Full Name
                </td>
                <td style={{ padding: '0.75rem 0', color: 'var(--innova-gray-900)', fontWeight: 600 }}>
                  {user?.name}
                </td>
              </tr>
              <tr style={{ borderBottom: '1px solid var(--innova-gray-100)' }}>
                <td style={{ padding: '0.75rem 0', fontWeight: 600, color: 'var(--innova-gray-600)' }}>
                  Email Address
                </td>
                <td style={{ padding: '0.75rem 0', color: 'var(--innova-gray-900)' }}>
                  {user?.email}
                </td>
              </tr>
              <tr style={{ borderBottom: '1px solid var(--innova-gray-100)' }}>
                <td style={{ padding: '0.75rem 0', fontWeight: 600, color: 'var(--innova-gray-600)' }}>
                  Authentication Guard
                </td>
                <td style={{ padding: '0.75rem 0' }}>
                  <span className="innova-badge innova-badge-primary">admin</span>
                </td>
              </tr>
              <tr>
                <td style={{ padding: '0.75rem 0', fontWeight: 600, color: 'var(--innova-gray-600)' }}>
                  Token Type
                </td>
                <td style={{ padding: '0.75rem 0', color: 'var(--innova-gray-900)' }}>
                  Laravel Sanctum (Bearer)
                </td>
              </tr>
            </tbody>
          </table>
        </Card>

        {/* Roles & Permissions */}
        <Card title="Spatie RBAC Privileges" subtitle="Assigned roles and granular permission capabilities">
          <div style={{ marginBottom: '1.25rem' }}>
            <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--innova-gray-700)', marginBottom: '0.5rem' }}>
              Assigned Roles:
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.5rem' }}>
              {user?.roles && user.roles.length > 0 ? (
                user.roles.map((r) => (
                  <span key={r} className="innova-badge innova-badge-primary" style={{ padding: '0.35rem 0.65rem' }}>
                    {r}
                  </span>
                ))
              ) : (
                <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
                  None assigned
                </span>
              )}
            </div>
          </div>

          <div>
            <div style={{ fontSize: '0.85rem', fontWeight: 600, color: 'var(--innova-gray-700)', marginBottom: '0.5rem' }}>
              Granted Permissions ({user?.permissions?.length || 0}):
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.35rem', maxHeight: '180px', overflowY: 'auto' }}>
              {user?.permissions && user.permissions.length > 0 ? (
                user.permissions.map((p) => (
                  <span
                    key={p}
                    className="innova-badge"
                    style={{
                      fontSize: '0.75rem',
                      backgroundColor: 'var(--innova-gray-100)',
                      color: 'var(--innova-gray-700)',
                    }}
                  >
                    {p}
                  </span>
                ))
              ) : (
                <span style={{ fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
                  No granular permissions granted directly.
                </span>
              )}
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
};

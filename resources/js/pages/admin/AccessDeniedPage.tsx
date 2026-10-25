import React from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { Card, Alert } from '../../components/common';

export const AccessDeniedPage: React.FC = () => {
  const { user, logout } = useAuth();

  return (
    <div style={{ maxWidth: '640px', margin: '3rem auto', padding: '0 1rem' }}>
      <Card
        title="403 — Administrator Access Required"
        subtitle="Cross-boundary security enforcement"
        footer={
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <Link to="/dashboard" className="innova-btn innova-btn-outline">
              Return to Dashboard
            </Link>
            <button
              type="button"
              className="innova-btn innova-btn-danger"
              onClick={() => logout()}
            >
              Logout & Switch Account
            </button>
          </div>
        }
      >
        <Alert type="error" title="Insufficient Guard Authorization">
          The React Admin Application Shell requires an authenticated <strong>Admin Guard</strong> identity.
          Your current session is authenticated as a <strong>{user?.is_admin ? 'Non-Privileged Admin' : 'Student Account'}</strong> ({user?.email}).
        </Alert>

        <p style={{ marginTop: '1rem', fontSize: '0.9rem', color: 'var(--innova-gray-600)' }}>
          Laravel's <code>EnsureAdminIdentity</code> middleware prevents student accounts from accessing administrative endpoints and views.
          To access the administrator workspace, please log out and authenticate using administrative credentials.
        </p>
      </Card>
    </div>
  );
};

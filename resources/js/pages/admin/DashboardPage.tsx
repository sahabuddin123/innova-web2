import React from 'react';
import { useAuth } from '../../hooks/useAuth';
import { Card, Alert } from '../../components/common';

export const DashboardPage: React.FC = () => {
  const { user } = useAuth();

  return (
    <div>
      <div style={{ marginBottom: '1.5rem' }}>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-900)' }}>
          Admin Dashboard
        </h1>
        <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
          Welcome back, {user?.name || 'Administrator'}. React Admin Application Shell (Phase 3.2).
        </p>
      </div>

      <Alert type="info" title="Application Shell Foundation Active">
        This is a placeholder landing page for the React Admin Application Shell. In strict compliance with Phase 3.2 scope boundaries, no business modules (courses, students, admissions, fees, accounting, SMS) or production KPIs have been migrated yet.
      </Alert>

      {/* Overview Stat Cards (Shell Verification) */}
      <div className="innova-grid" style={{ marginTop: '1.5rem', marginBottom: '1.5rem' }}>
        <div className="innova-stat-box">
          <div className="innova-stat-label">Session Identity</div>
          <div className="innova-stat-value" style={{ fontSize: '1.15rem' }}>
            {user?.name}
          </div>
          <span className="innova-badge innova-badge-primary" style={{ marginTop: '0.35rem' }}>
            {user?.roles?.[0] || 'Administrator'}
          </span>
        </div>

        <div className="innova-stat-box">
          <div className="innova-stat-label">Granted Permissions</div>
          <div className="innova-stat-value">
            {user?.permissions ? `${user.permissions.length}` : '0'}
          </div>
          <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', display: 'block', marginTop: '0.25rem' }}>
            Spatie Laravel-Permission
          </span>
        </div>

        <div className="innova-stat-box">
          <div className="innova-stat-label">Client Router</div>
          <div className="innova-stat-value" style={{ fontSize: '1.15rem' }}>
            React Router v6
          </div>
          <span className="innova-badge innova-badge-success" style={{ marginTop: '0.35rem' }}>
            Isolated /react-admin/*
          </span>
        </div>

        <div className="innova-stat-box">
          <div className="innova-stat-label">Legacy Status</div>
          <div className="innova-stat-value" style={{ fontSize: '1.15rem' }}>
            Zero Collisions
          </div>
          <span style={{ fontSize: '0.75rem', color: 'var(--innova-gray-500)', display: 'block', marginTop: '0.25rem' }}>
            Blade /admin/* intact
          </span>
        </div>
      </div>

      {/* Migration Roadmap Card */}
      <Card
        title="Upcoming Module Modernization Roadmap (Phase 3.3+)"
        subtitle="Business modules scheduled for gradual migration onto this React Admin Shell"
      >
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem' }}>
          <div style={{ padding: '1rem', border: '1px solid var(--innova-gray-200)', borderRadius: '8px' }}>
            <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)', marginBottom: '0.25rem' }}>
              Phase 3.3: Academic & Course Catalogs
            </div>
            <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
              Course categories, course management, sessions, and batch schedules.
            </p>
          </div>

          <div style={{ padding: '1rem', border: '1px solid var(--innova-gray-200)', borderRadius: '8px' }}>
            <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)', marginBottom: '0.25rem' }}>
              Phase 3.4: Student Lifecycle & Admissions
            </div>
            <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
              Student directory, enrollment records, multi-state status updates, ID cards.
            </p>
          </div>

          <div style={{ padding: '1rem', border: '1px solid var(--innova-gray-200)', borderRadius: '8px' }}>
            <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)', marginBottom: '0.25rem' }}>
              Phase 3.5: Accounting, Ledgers & Bank Loans
            </div>
            <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
              Incomes, expenses, multi-branch cash summaries, bank loans, and EMI schedules.
            </p>
          </div>

          <div style={{ padding: '1rem', border: '1px solid var(--innova-gray-200)', borderRadius: '8px' }}>
            <div style={{ fontWeight: 600, color: 'var(--innova-gray-900)', marginBottom: '0.25rem' }}>
              Phase 3.6: SMS Gateway & Settings
            </div>
            <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-600)' }}>
              Transactional SMS templates, delivery logs, and system settings management.
            </p>
          </div>
        </div>
      </Card>
    </div>
  );
};

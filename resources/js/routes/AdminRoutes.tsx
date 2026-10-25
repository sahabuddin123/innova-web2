import React from 'react';
import { Routes, Route, Navigate, useLocation } from 'react-router-dom';
import { AdminLayout } from '../components/admin/AdminLayout';
import {
  DashboardPage,
  ProfilePage,
  AccessDeniedPage,
  NotFoundPage,
  CourseListPage,
  BatchListPage,
  StudentListPage,
  AdmissionListPage,
  CertificateListPage,
  FeeListPage,
} from '../pages/admin';
import { Card, Alert } from '../components/common';

/**
 * Representative placeholder for unmigrated business modules.
 */
const PlaceholderModule: React.FC<{ name: string; phase: string }> = ({ name, phase }) => {
  const location = useLocation();

  return (
    <div>
      <div style={{ marginBottom: '1.5rem' }}>
        <h1 style={{ fontSize: '1.75rem', fontWeight: 700, margin: '0 0 0.35rem 0', color: 'var(--innova-gray-900)' }}>
          {name}
        </h1>
        <p style={{ margin: 0, fontSize: '0.9rem', color: 'var(--innova-gray-500)' }}>
          Business Module Route: <code>{location.pathname}</code>
        </p>
      </div>

      <Card title={`Module Scheduled for ${phase}`} subtitle="Incremental modernization plan">
        <Alert type="info" title="Under Modernization">
          In strict compliance with modernization scope boundaries, this module is operational in production via the existing Blade admin interface and will be migrated in <strong>{phase}</strong>.
        </Alert>

        <p style={{ marginTop: '1rem', fontSize: '0.9rem', color: 'var(--innova-gray-600)' }}>
          The REST API endpoints for this module are operational under <code>/api/v1/*</code>.
          React UI components and forms will be created when this module's dedicated migration phase begins.
        </p>
      </Card>
    </div>
  );
};

export const AdminRoutes: React.FC = () => {
  return (
    <Routes>
      <Route element={<AdminLayout />}>
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard" element={<DashboardPage />} />
        <Route path="profile" element={<ProfilePage />} />
        <Route path="access-denied" element={<AccessDeniedPage />} />

        {/* Migrated Academic, Student & Financial Interfaces (Phase 3.3 - 3.7) */}
        <Route path="courses" element={<CourseListPage />} />
        <Route path="batches" element={<BatchListPage />} />
        <Route path="students" element={<StudentListPage />} />
        <Route path="admissions" element={<AdmissionListPage />} />
        <Route path="certificates" element={<CertificateListPage />} />
        <Route path="fees" element={<FeeListPage />} />
        <Route
          path="accounting"
          element={<PlaceholderModule name="Accounts & Institutional Ledgers" phase="Phase 3.8" />}
        />
        <Route
          path="roles"
          element={<PlaceholderModule name="Roles & Administration" phase="Phase 3.2 Security Gate" />}
        />
        <Route
          path="settings"
          element={<PlaceholderModule name="SMS Gateway & System Settings" phase="Phase 3.10" />}
        />

        {/* Catch-all 404 within Admin route namespace */}
        <Route path="*" element={<NotFoundPage />} />
      </Route>
    </Routes>
  );
};

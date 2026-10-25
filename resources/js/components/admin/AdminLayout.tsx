import React, { useState } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import { AdminSidebar } from './AdminSidebar';
import { AdminHeader } from './AdminHeader';
import { AdminContent } from './AdminContent';
import { MobileNavigation } from './MobileNavigation';
import { LoadingSpinner, Alert } from '../common';
import { AuthDemo } from '../auth/AuthDemo';

export const AdminLayout: React.FC = () => {
  const { user, isAuthenticated, isLoading } = useAuth();
  const [collapsed, setCollapsed] = useState<boolean>(false);
  const [mobileOpen, setMobileOpen] = useState<boolean>(false);

  // Authentication Loading State
  if (isLoading) {
    return (
      <div
        style={{
          minHeight: '100vh',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          backgroundColor: 'var(--innova-bg-page, #f8fafc)',
        }}
      >
        <LoadingSpinner size="lg" label="Verifying Administrator session..." />
      </div>
    );
  }

  // Not Authenticated -> Render Gate with AuthDemo for easy testing
  if (!isAuthenticated || !user) {
    return (
      <div style={{ maxWidth: '700px', margin: '3rem auto', padding: '0 1rem' }}>
        <Alert type="warning" title="Authentication Required">
          You are attempting to access the React Admin Dashboard Shell. Please authenticate as an Administrator using the form below.
        </Alert>
        <div style={{ marginTop: '1.5rem' }}>
          <AuthDemo />
        </div>
      </div>
    );
  }

  // Student Account attempting Admin Shell -> Forbidden
  if (!user.is_admin) {
    return <Navigate to="/access-denied" replace />;
  }

  return (
    <div
      className="innova-admin-shell"
      style={{
        display: 'flex',
        minHeight: '100vh',
        backgroundColor: 'var(--innova-bg-page, #f8fafc)',
        color: 'var(--innova-gray-900)',
      }}
    >
      {/* Skip to Main Content Link for Keyboard Accessibility */}
      <a
        href="#main-content"
        className="innova-skip-link"
        style={{
          position: 'absolute',
          top: '-100px',
          left: '1rem',
          backgroundColor: 'var(--innova-primary)',
          color: '#ffffff',
          padding: '0.5rem 1rem',
          borderRadius: '4px',
          zIndex: 100,
          fontWeight: 600,
          textDecoration: 'none',
          transition: 'top 0.15s ease',
        }}
        onFocus={(e) => {
          e.currentTarget.style.top = '1rem';
        }}
        onBlur={(e) => {
          e.currentTarget.style.top = '-100px';
        }}
      >
        Skip to main content
      </a>

      {/* Desktop Collapsible Sidebar */}
      <AdminSidebar
        collapsed={collapsed}
        onToggleCollapse={() => setCollapsed(!collapsed)}
      />

      {/* Mobile Slide-over Drawer */}
      <MobileNavigation
        open={mobileOpen}
        onClose={() => setMobileOpen(false)}
      />

      {/* Right Column: Sticky Header + Main Content */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <AdminHeader onOpenMobileNav={() => setMobileOpen(true)} />
        <AdminContent>
          <Outlet />
        </AdminContent>
      </div>
    </div>
  );
};

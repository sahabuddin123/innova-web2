import React from 'react';
import { useAuth } from '../../hooks/useAuth';
import { AdminNavigation } from './AdminNavigation';

interface AdminSidebarProps {
  collapsed: boolean;
  onToggleCollapse: () => void;
}

export const AdminSidebar: React.FC<AdminSidebarProps> = ({
  collapsed,
  onToggleCollapse,
}) => {
  const { user, logout } = useAuth();

  return (
    <aside
      aria-label="Admin Sidebar Navigation"
      className={`innova-admin-sidebar ${collapsed ? 'collapsed' : ''}`}
      style={{
        width: collapsed ? '72px' : '260px',
        backgroundColor: 'var(--innova-sidebar-bg, #ffffff)',
        borderRight: '1px solid var(--innova-gray-200)',
        display: 'flex',
        flexDirection: 'column',
        transition: 'width 0.2s cubic-bezier(0.4, 0, 0.2, 1)',
        height: '100vh',
        position: 'sticky',
        top: 0,
        zIndex: 30,
        overflowX: 'hidden',
      }}
    >
      {/* Brand Header */}
      <div
        style={{
          height: '64px',
          padding: '0 1rem',
          display: 'flex',
          alignItems: 'center',
          justifyContent: collapsed ? 'center' : 'space-between',
          borderBottom: '1px solid var(--innova-gray-200)',
          flexShrink: 0,
        }}
      >
        {!collapsed && (
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
            <div
              style={{
                width: '32px',
                height: '32px',
                borderRadius: '8px',
                backgroundColor: 'var(--innova-primary)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#ffffff',
                fontWeight: 700,
                fontSize: '1rem',
              }}
            >
              I
            </div>
            <div>
              <div style={{ fontWeight: 700, fontSize: '0.95rem', color: 'var(--innova-gray-900)' }}>
                INNOVA BD
              </div>
              <div style={{ fontSize: '0.7rem', color: 'var(--innova-gray-500)' }}>
                React Admin Shell
              </div>
            </div>
          </div>
        )}

        <button
          type="button"
          onClick={onToggleCollapse}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          style={{
            background: 'none',
            border: 'none',
            color: 'var(--innova-gray-500)',
            cursor: 'pointer',
            padding: '0.4rem',
            borderRadius: '6px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
        >
          {collapsed ? (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="13 17 18 12 13 7" />
              <polyline points="6 17 11 12 6 7" />
            </svg>
          ) : (
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <polyline points="11 17 6 12 11 7" />
              <polyline points="18 17 13 12 18 7" />
            </svg>
          )}
        </button>
      </div>

      {/* Scrollable Navigation Area */}
      <div style={{ flex: 1, overflowY: 'auto', overflowX: 'hidden' }}>
        <AdminNavigation collapsed={collapsed} />
      </div>

      {/* User Footer Summary */}
      {user && (
        <div
          style={{
            padding: collapsed ? '0.75rem 0' : '0.85rem 1rem',
            borderTop: '1px solid var(--innova-gray-200)',
            backgroundColor: 'var(--innova-gray-50)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: collapsed ? 'center' : 'space-between',
            flexShrink: 0,
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem', overflow: 'hidden' }}>
            <div
              style={{
                width: '34px',
                height: '34px',
                borderRadius: '50%',
                backgroundColor: 'var(--innova-primary-light, #e0e7ff)',
                color: 'var(--innova-primary, #4f46e5)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 600,
                fontSize: '0.85rem',
                flexShrink: 0,
              }}
            >
              {user.name.charAt(0).toUpperCase()}
            </div>
            {!collapsed && (
              <div style={{ overflow: 'hidden' }}>
                <div style={{ fontSize: '0.825rem', fontWeight: 600, color: 'var(--innova-gray-900)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {user.name}
                </div>
                <div style={{ fontSize: '0.7rem', color: 'var(--innova-gray-500)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {user.roles?.[0] || (user.is_admin ? 'Administrator' : 'Student')}
                </div>
              </div>
            )}
          </div>

          {!collapsed && (
            <button
              type="button"
              onClick={() => logout()}
              title="Logout session"
              aria-label="Logout session"
              style={{
                background: 'none',
                border: 'none',
                color: 'var(--innova-gray-500)',
                cursor: 'pointer',
                padding: '0.35rem',
                borderRadius: '6px',
              }}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
            </button>
          )}
        </div>
      )}
    </aside>
  );
};

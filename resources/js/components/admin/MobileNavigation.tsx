import React, { useEffect } from 'react';
import { AdminNavigation } from './AdminNavigation';
import { useAuth } from '../../hooks/useAuth';

interface MobileNavigationProps {
  open: boolean;
  onClose: () => void;
}

export const MobileNavigation: React.FC<MobileNavigationProps> = ({ open, onClose }) => {
  const { user, logout } = useAuth();

  // Close drawer on ESC key
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && open) {
        onClose();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Mobile Navigation Drawer"
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 50,
        display: 'flex',
      }}
    >
      {/* Backdrop */}
      <div
        onClick={onClose}
        style={{
          position: 'fixed',
          inset: 0,
          backgroundColor: 'rgba(0, 0, 0, 0.5)',
          backdropFilter: 'blur(2px)',
          animation: 'innovaFadeIn 0.2s ease',
        }}
      />

      {/* Drawer content */}
      <div
        style={{
          position: 'relative',
          width: '280px',
          maxWidth: '85vw',
          height: '100%',
          backgroundColor: 'var(--innova-sidebar-bg, #ffffff)',
          borderRight: '1px solid var(--innova-gray-200)',
          display: 'flex',
          flexDirection: 'column',
          zIndex: 51,
          boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.25)',
          animation: 'innovaSlideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1)',
        }}
      >
        {/* Header */}
        <div
          style={{
            height: '64px',
            padding: '0 1rem',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            borderBottom: '1px solid var(--innova-gray-200)',
          }}
        >
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
              }}
            >
              I
            </div>
            <div style={{ fontWeight: 700, fontSize: '0.95rem', color: 'var(--innova-gray-900)' }}>
              INNOVA BD
            </div>
          </div>

          <button
            type="button"
            onClick={onClose}
            aria-label="Close navigation"
            style={{
              background: 'none',
              border: 'none',
              color: 'var(--innova-gray-500)',
              cursor: 'pointer',
              padding: '0.4rem',
              borderRadius: '6px',
            }}
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        {/* Scrollable nav list */}
        <div style={{ flex: 1, overflowY: 'auto' }}>
          <AdminNavigation collapsed={false} onItemClick={onClose} />
        </div>

        {/* User Footer */}
        {user && (
          <div
            style={{
              padding: '0.85rem 1rem',
              borderTop: '1px solid var(--innova-gray-200)',
              backgroundColor: 'var(--innova-gray-50)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between',
            }}
          >
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.65rem' }}>
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
                }}
              >
                {user.name.charAt(0).toUpperCase()}
              </div>
              <div>
                <div style={{ fontSize: '0.825rem', fontWeight: 600, color: 'var(--innova-gray-900)' }}>
                  {user.name}
                </div>
                <div style={{ fontSize: '0.7rem', color: 'var(--innova-gray-500)' }}>
                  {user.roles?.[0] || 'Administrator'}
                </div>
              </div>
            </div>

            <button
              type="button"
              onClick={() => {
                logout();
                onClose();
              }}
              title="Logout session"
              aria-label="Logout session"
              style={{
                background: 'none',
                border: 'none',
                color: 'var(--innova-gray-500)',
                cursor: 'pointer',
                padding: '0.35rem',
              }}
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                <polyline points="16 17 21 12 16 7" />
                <line x1="21" y1="12" x2="9" y2="12" />
              </svg>
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

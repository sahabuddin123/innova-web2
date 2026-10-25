import React from 'react';
import { NavLink } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

interface NavItem {
  label: string;
  to: string;
  icon: React.ReactNode;
  badge?: string;
  isPlaceholder?: boolean;
  requiredRole?: string;
  requiredPermission?: string;
}

interface NavSection {
  title: string;
  items: NavItem[];
}

interface AdminNavigationProps {
  collapsed?: boolean;
  onItemClick?: () => void;
}

export const AdminNavigation: React.FC<AdminNavigationProps> = ({
  collapsed = false,
  onItemClick,
}) => {
  const { user, hasRole, hasPermission } = useAuth();

  const sections: NavSection[] = [
    {
      title: 'Core Administration',
      items: [
        {
          label: 'Dashboard',
          to: '/dashboard',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="3" width="7" height="9" />
              <rect x="14" y="3" width="7" height="5" />
              <rect x="14" y="12" width="7" height="9" />
              <rect x="3" y="16" width="7" height="5" />
            </svg>
          ),
        },
        {
          label: 'Administrator Profile',
          to: '/profile',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
              <circle cx="12" cy="7" r="4" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'Academic & Training',
      items: [
        {
          label: 'Courses & Categories',
          to: '/courses',
          isPlaceholder: true,
          badge: 'Phase 3.3',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
            </svg>
          ),
        },
        {
          label: 'Batches & Academic Terms',
          to: '/batches',
          isPlaceholder: true,
          badge: 'Phase 3.3',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'Students & Lifecycle',
      items: [
        {
          label: 'Student Directory',
          to: '/students',
          isPlaceholder: true,
          badge: 'Phase 3.4',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'Financial Management',
      items: [
        {
          label: 'Accounts & Ledgers',
          to: '/accounting',
          isPlaceholder: true,
          badge: 'Phase 3.5',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <line x1="12" y1="1" x2="12" y2="23" />
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
            </svg>
          ),
        },
      ],
    },
    {
      title: 'System & Security',
      items: [
        {
          label: 'RBAC Roles & Admins',
          to: '/roles',
          requiredRole: 'super-admin',
          isPlaceholder: true,
          badge: 'Super Admin',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
          ),
        },
        {
          label: 'SMS & Settings',
          to: '/settings',
          requiredPermission: 'manage-settings',
          isPlaceholder: true,
          badge: 'Settings',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="3" />
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z" />
            </svg>
          ),
        },
      ],
    },
  ];

  return (
    <nav aria-label="Admin Navigation" className="innova-admin-nav" style={{ padding: '0.75rem 0' }}>
      {sections.map((section) => {
        // Filter items based on user roles and permissions
        const visibleItems = section.items.filter((item) => {
          if (!user) return false;
          if (item.requiredRole && !hasRole(item.requiredRole)) return false;
          if (item.requiredPermission && !hasPermission(item.requiredPermission)) return false;
          return true;
        });

        if (visibleItems.length === 0) return null;

        return (
          <div key={section.title} style={{ marginBottom: '1.25rem' }}>
            {!collapsed && (
              <div
                style={{
                  padding: '0 1rem 0.5rem 1rem',
                  fontSize: '0.7rem',
                  fontWeight: 700,
                  textTransform: 'uppercase',
                  letterSpacing: '0.05em',
                  color: 'var(--innova-gray-400)',
                }}
              >
                {section.title}
              </div>
            )}

            <ul style={{ listStyle: 'none', margin: 0, padding: '0 0.5rem', display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
              {visibleItems.map((item) => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    onClick={onItemClick}
                    className={({ isActive }) =>
                      `innova-nav-link ${isActive ? 'active' : ''}`
                    }
                    title={collapsed ? item.label : undefined}
                    style={({ isActive }) => ({
                      display: 'flex',
                      alignItems: 'center',
                      gap: '0.75rem',
                      padding: collapsed ? '0.65rem 0' : '0.65rem 0.75rem',
                      justifyContent: collapsed ? 'center' : 'flex-start',
                      borderRadius: '8px',
                      textDecoration: 'none',
                      fontSize: '0.875rem',
                      fontWeight: isActive ? 600 : 500,
                      color: isActive ? 'var(--innova-primary)' : 'var(--innova-gray-700)',
                      backgroundColor: isActive ? 'var(--innova-primary-light, #e0e7ff)' : 'transparent',
                      transition: 'background-color 0.15s ease, color 0.15s ease',
                    })}
                  >
                    <span style={{ display: 'flex', alignItems: 'center', flexShrink: 0 }}>
                      {item.icon}
                    </span>
                    {!collapsed && (
                      <span style={{ flex: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {item.label}
                      </span>
                    )}
                    {!collapsed && item.badge && (
                      <span
                        className="innova-badge"
                        style={{
                          fontSize: '0.65rem',
                          padding: '0.15rem 0.4rem',
                          backgroundColor: item.isPlaceholder ? '#f1f5f9' : 'var(--innova-primary)',
                          color: item.isPlaceholder ? '#64748b' : '#ffffff',
                          borderRadius: '9999px',
                        }}
                      >
                        {item.badge}
                      </span>
                    )}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        );
      })}
    </nav>
  );
};

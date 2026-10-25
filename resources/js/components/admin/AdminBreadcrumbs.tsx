import React from 'react';
import { useLocation, Link } from 'react-router-dom';

const ROUTE_NAME_MAP: Record<string, string> = {
  dashboard: 'Dashboard',
  profile: 'Administrator Profile',
  'access-denied': 'Access Denied',
  courses: 'Course Management (Placeholder)',
  students: 'Student Lifecycle (Placeholder)',
  accounting: 'Accounting & Ledgers (Placeholder)',
  sms: 'SMS Gateway (Placeholder)',
  settings: 'System Settings (Placeholder)',
};

/**
 * Dynamic, accessible breadcrumbs trail for React Admin Shell.
 */
export const AdminBreadcrumbs: React.FC = () => {
  const location = useLocation();
  const pathnames = location.pathname.split('/').filter(Boolean);

  return (
    <nav aria-label="Breadcrumb" className="innova-breadcrumbs" style={{ margin: 0, padding: 0 }}>
      <ol
        style={{
          display: 'flex',
          alignItems: 'center',
          listStyle: 'none',
          margin: 0,
          padding: 0,
          gap: '0.5rem',
          fontSize: '0.85rem',
          color: 'var(--innova-gray-500)',
        }}
      >
        <li>
          <Link
            to="/dashboard"
            style={{
              color: 'var(--innova-gray-600)',
              textDecoration: 'none',
              fontWeight: 500,
            }}
          >
            Admin Shell
          </Link>
        </li>

        {pathnames.map((segment, index) => {
          const to = `/${pathnames.slice(0, index + 1).join('/')}`;
          const isLast = index === pathnames.length - 1;
          const label = ROUTE_NAME_MAP[segment] || segment.charAt(0).toUpperCase() + segment.slice(1);

          return (
            <React.Fragment key={to}>
              <li aria-hidden="true" style={{ color: 'var(--innova-gray-400)' }}>
                /
              </li>
              <li>
                {isLast ? (
                  <span
                    aria-current="page"
                    style={{
                      color: 'var(--innova-gray-900)',
                      fontWeight: 600,
                    }}
                  >
                    {label}
                  </span>
                ) : (
                  <Link
                    to={to}
                    style={{
                      color: 'var(--innova-gray-600)',
                      textDecoration: 'none',
                    }}
                  >
                    {label}
                  </Link>
                )}
              </li>
            </React.Fragment>
          );
        })}
      </ol>
    </nav>
  );
};

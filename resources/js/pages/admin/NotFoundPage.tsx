import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Card } from '../../components/common';

export const NotFoundPage: React.FC = () => {
  const location = useLocation();

  return (
    <div style={{ maxWidth: '600px', margin: '3rem auto', padding: '0 1rem' }}>
      <Card
        title="404 — Admin Route Not Found"
        subtitle="The requested subpath does not exist in the React Admin Shell"
        footer={
          <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
            <Link to="/dashboard" className="innova-btn innova-btn-primary">
              Back to Dashboard
            </Link>
          </div>
        }
      >
        <p style={{ margin: '0 0 1rem 0', fontSize: '0.95rem', color: 'var(--innova-gray-700)' }}>
          No route matched the path: <code>{location.pathname}</code>
        </p>
        <p style={{ margin: 0, fontSize: '0.85rem', color: 'var(--innova-gray-500)' }}>
          If you were looking for an unmigrated business module, please access it via the existing Blade admin navigation.
        </p>
      </Card>
    </div>
  );
};

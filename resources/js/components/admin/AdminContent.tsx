import React, { ReactNode } from 'react';

interface AdminContentProps {
  children: ReactNode;
}

/**
 * Main scrollable viewport container for Admin Shell pages.
 */
export const AdminContent: React.FC<AdminContentProps> = ({ children }) => {
  return (
    <main
      id="main-content"
      tabIndex={-1}
      className="innova-admin-content"
      style={{
        flex: 1,
        padding: '1.75rem',
        backgroundColor: 'var(--innova-bg-page, #f8fafc)',
        minHeight: 'calc(100vh - 64px)',
        outline: 'none',
        overflowY: 'auto',
      }}
    >
      <div style={{ maxWidth: '1280px', margin: '0 auto' }}>
        {children}
      </div>
    </main>
  );
};

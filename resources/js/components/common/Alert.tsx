import React from 'react';

export interface AlertProps {
  type?: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  children: React.ReactNode;
  className?: string;
}

export const Alert: React.FC<AlertProps> = ({
  type = 'info',
  title,
  children,
  className = '',
}) => {
  return (
    <div className={`innova-alert innova-alert-${type} ${className}`} role="alert">
      {title && <strong className="innova-alert-title">{title}</strong>}
      <div className="innova-alert-content">{children}</div>
    </div>
  );
};

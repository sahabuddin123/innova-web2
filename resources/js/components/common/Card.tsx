import React from 'react';

export interface CardProps {
  title?: string;
  subtitle?: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
  className?: string;
}

export const Card: React.FC<CardProps> = ({
  title,
  subtitle,
  children,
  footer,
  className = '',
}) => {
  return (
    <div className={`innova-card ${className}`}>
      {(title || subtitle) && (
        <div className="innova-card-header">
          {title && <h3 className="innova-card-title">{title}</h3>}
          {subtitle && <p className="innova-card-subtitle">{subtitle}</p>}
        </div>
      )}
      <div className="innova-card-body">{children}</div>
      {footer && <div className="innova-card-footer">{footer}</div>}
    </div>
  );
};

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Dashboard } from '@/pages/Dashboard';

const el = document.getElementById('app');
if (!el) throw new Error('#app not found');

createRoot(el).render(
    <StrictMode>
        <Dashboard />
    </StrictMode>
);

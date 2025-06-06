import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css';
import App from './App.tsx';
import { CentraleProvider } from './CentraleContext.tsx';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
      <CentraleProvider>
          <App />
      </CentraleProvider>
  </StrictMode>,
)

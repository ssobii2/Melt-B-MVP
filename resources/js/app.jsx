import React from 'react';
import { createRoot } from "react-dom/client";
import { BrowserRouter } from 'react-router-dom';
import { AuthProvider } from "./contexts/AuthContext";
import Router from "./components/Router";

// Get the root element
const el = document.getElementById('app');

if (el) {
    createRoot(el).render(
        <BrowserRouter>
            <AuthProvider>
                <Router />
            </AuthProvider>
        </BrowserRouter>
    );
}

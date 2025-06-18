import React from 'react';
import { Inertia } from "@inertiajs/inertia";

export default function Home({ message }) {
    return (
        <div className="flex items-center justify-center min-h-screen bg-gradient-to-r from-purple-500 to-indigo-600">
            <div className="text-center">
                <h1 className="text-4xl font-bold text-white mb-4">
                    🚀 Hello from React + Tailwind!
                </h1>
                <p className="text-lg text-white opacity-90">{message}</p>
            </div>
        </div>
    );
}

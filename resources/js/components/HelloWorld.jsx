import React from 'react';

export default function HelloWorld() {
  return (
    <div className="flex items-center justify-center min-h-screen bg-gradient-to-r from-purple-500 to-indigo-600">
      <div className="text-center">
        <h1 className="text-4xl font-bold text-white mb-4">🚀 Hello from React + Tailwind!</h1>
        <p className="text-lg text-white opacity-90">If you see this with gradient background, Tailwind is working!</p>
      </div>
    </div>
  );
}

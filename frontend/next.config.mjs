/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  output: 'standalone',
  images: {
    unoptimized: true,
    remotePatterns: [
      {
        protocol: 'https',
        hostname: 'api.example.test',
        pathname: '/storage/**',
      },
      {
        protocol: 'http',
        hostname: 'localhost',
        pathname: '/storage/**',
      },
    ],
  },
};

export default nextConfig;

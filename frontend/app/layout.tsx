// app/layout.tsx
import type { Metadata } from "next";
import { AppRouterCacheProvider } from "@mui/material-nextjs/v13-appRouter";
import { StyledRoot } from "./ui/StyledRoot";

import "./ui/globals.css";

export const metadata: Metadata = {
  title: "VGeneral Contractors Software",
  description: "VGeneral Contractors Software",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body>
        <AppRouterCacheProvider>
          <StyledRoot>{children}</StyledRoot>
        </AppRouterCacheProvider>
      </body>
    </html>
  );
}

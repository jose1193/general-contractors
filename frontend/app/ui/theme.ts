// app/ui/theme.ts
"use client";

import { Exo } from "next/font/google";
import { createTheme, Theme } from "@mui/material/styles";
import { grey } from "@mui/material/colors";

const exo = Exo({
  weight: ["300", "400", "500", "700"],
  subsets: ["latin"],
  display: "swap",
});

const lightTheme: Theme = createTheme({
  typography: {
    fontFamily: exo.style.fontFamily,
  },
  palette: {
    mode: "light",
    primary: {
      main: "#212121", // Example primary color
    },
    secondary: {
      main: "#0c181c", // Example secondary color
    },
  },
});

const darkTheme: Theme = createTheme({
  typography: {
    fontFamily: exo.style.fontFamily,
  },
  palette: {
    mode: "dark",
    primary: {
      main: "#FFD700", //deepOrange[500], // Dark mode primary color
    },
    secondary: {
      main: grey[500], // Dark mode secondary color
    },
  },
});

export { lightTheme, darkTheme };

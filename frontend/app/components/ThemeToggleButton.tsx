// app/components/ThemeToggleButton.tsx
"use client";
import React from "react";
import { Button } from "@mui/material";
import { useTheme } from "../ui/StyledRoot";
import DarkModeIcon from "@mui/icons-material/DarkMode";
import Brightness7Icon from "@mui/icons-material/Brightness7";

const ThemeToggleButton: React.FC = () => {
  const { darkMode, toggleTheme } = useTheme();

  return (
    <Button
      variant="text"
      color="primary"
      startIcon={darkMode ? <Brightness7Icon /> : <DarkModeIcon />}
      onClick={toggleTheme}
    ></Button>
  );
};

export default ThemeToggleButton;

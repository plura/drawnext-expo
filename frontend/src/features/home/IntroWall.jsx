// src/features/intro/IntroWall.jsx
import React from "react";
import { Link } from "react-router-dom";
import Wall from "@/components/wall/Wall";
import { Button } from "@/components/ui/button";
import AppLogoBrand from "@/assets/app_logo_brand.svg?react";
import { useWallDimensions } from "@/hooks/useWallDimensions";

export default function IntroWall() {
  // Auto rows/cols based on screen size
  const { rows, cols } = useWallDimensions();
  const gap = "0.25rem"; // tweak if you want this to match ExhibitionWall

  return (
    <div className="relative h-dvh bg-brand">
      {/* Wall fills the parent container */}
      <Wall
        rows={rows}
        cols={cols}
        gap={gap}
        rand
        intervalMs={45000}
        tileSpreadMs={4000}
      />

      {/* Fullscreen overlay with centered content */}
      <div className="absolute inset-0 h-dvh w-dvw flex items-center justify-center px-4 backdrop-blur-md">
        <div
          className="
            flex flex-col items-center justify-center gap-6
            rounded-lg p-6 md:p-8
            text-center
          "
        >
          <AppLogoBrand className="h-8 w-auto md:h-10" />

          <h1 className="max-w-2xl text-balance text-lg font-semibold md:text-2xl">
            What would the future be like in 100 years?
          </h1>

          <Link to="/wall">
            <Button
              size="lg"
              className="bg-brand text-foreground hover:bg-brand/90"
            >
              Drawings Wall
            </Button>
          </Link>
        </div>
      </div>
    </div>
  );
}

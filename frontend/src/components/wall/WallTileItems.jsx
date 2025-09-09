// src/components/wall/WallTileItems.jsx
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import WallTileItem from "./WallTileItem";

export default function WallTileItems({ item }) {
  const prefersReduced = useReducedMotion();
  const duration = prefersReduced ? 0.12 : 0.45;

  const variants = {
    initial: { opacity: 0, y: prefersReduced ? 0 : "-100%" }, // animate on first mount
    animate: { opacity: 1, y: 0 },
    exit:    { opacity: 0, y: prefersReduced ? 0 : "100%" },
  };

  return (
    <div className="absolute inset-0">
      {/* IMPORTANT: initial=true so the first items animate in */}
      <AnimatePresence initial>
        {item ? (
          <motion.div
            key={item.drawing_id ?? item.id ?? Math.random()}
            className="absolute inset-0 will-change-transform will-change-opacity"
            variants={variants}
            initial="initial"
            animate="animate"
            exit="exit"
            transition={{ duration, ease: [0.22, 0.16, 0.2, 0.98] }}
          >
            <WallTileItem item={item} />
          </motion.div>
        ) : null}
      </AnimatePresence>
    </div>
  );
}

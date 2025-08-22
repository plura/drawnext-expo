// src/features/submission/useSubmissionStore.js
import { useReducer } from 'react';

export const initialState = {
  file: null,
  notebookId: null,
  sectionId: null,
  page: '',
  neighbors: [], // [{ section_id, page }]
  email: '',

  // two-phase upload
  uploadToken: null,      // string token from /api/images/temp
  imageMeta: null         // { width, height, hash }
};

function reducer(state, action) {
  switch (action.type) {
    case 'PATCH':
      // merge partial updates into the state
      return { ...state, ...action.payload };
    case 'RESET':
      // reset to initial values (keep notebook if preselected via QR)
      return { ...initialState, notebookId: state.notebookId };
    default:
      return state;
  }
}

export function useSubmissionStore() {
  const [state, dispatch] = useReducer(reducer, initialState);

  const patch = (payload) => dispatch({ type: 'PATCH', payload });
  const reset = () => dispatch({ type: 'RESET' });

  return { state, patch, reset, dispatch };
}

// CentraleContext.tsx
import { createContext, useContext, useState } from "react";

type CentraleContextType = {
    refreshFlag: number;
    reloadSidebar: () => void;
};

const CentraleContext = createContext<CentraleContextType>({
    refreshFlag: 0,
    reloadSidebar: () => {},
});

export const useCentraleContext = () => useContext(CentraleContext);

export const CentraleProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
    const [refreshFlag, setRefreshFlag] = useState(0);

    const reloadSidebar = () => {
        setRefreshFlag((prev) => prev + 1);
    };

    return (
        <CentraleContext.Provider value={{ refreshFlag, reloadSidebar }}>
            {children}
        </CentraleContext.Provider>
    );
};

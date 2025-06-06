import './App.css'
import Sidebar from "./Sidebar.tsx";
import {BrowserRouter, Route, Routes} from "react-router-dom";
import Menu from "./Menu.tsx";
import CalculPage from "./CalculPage.tsx";
import ConfigPage from "./ConfigPage.tsx";

function App() {
    return (
        <BrowserRouter basename="/calculPrix">
            <div className="appContainer">
                <Sidebar />
                <div className="mainContent">
                    <Routes>
                        <Route path="/" element={<Menu />} />
                        <Route path="/:nom/config" element={<ConfigPage />} />
                        <Route path="/:nom/calcul" element={<CalculPage />} />
                    </Routes>
                </div>
            </div>
        </BrowserRouter>
    );
}

export default App;

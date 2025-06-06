import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useCentraleContext } from "./CentraleContext";
import "./Sidebar.css";

export default function Sidebar() {
    const [centrales, setCentrales] = useState<string[]>([]);
    const { refreshFlag } = useCentraleContext();

    const fetchCentrales = () => {
        fetch("http://localhost/manipRTE.php?action=GetCentrales")
            .then((res) => res.json())
            .then(setCentrales)
            .catch((err) => {
                console.error("Erreur lors du chargement des centrales :", err);
            });
    };

    useEffect(() => {
        fetchCentrales();
    }, [refreshFlag]);

    const handleAjouter = async () => {
        await fetch("http://localhost/manipRTE.php?action=NouvelleCentrale");
        fetchCentrales(); // Recharge après ajout
    };

    return (
        <div className="sidebar">
            <div className="sidebarElement" onClick={handleAjouter}>Ajouter</div>
            {centrales.map((nom, i) => {
                const url = `/${nom}/config`;
                return (
                    <Link to={url} key={i} className="sidebarElement">
                        {nom}
                    </Link>
                );
            })}
        </div>
    );
}

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:autrex/constants/icons.dart';
import 'package:autrex/constants/size.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({Key? key}) : super(key: key);

  @override
  _FavoritesScreenState createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  int _selectedIndex = 2; // Index for 'Favorites' in BottomNavigationBar

  List<String> favorites = []; // List to store favorite items

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
      // Handle navigation or other state changes as needed
      // For example, navigate to different screens based on index
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Color(0xFFFFC850), // Adjust to match your theme
        appBar: AppBar(
          backgroundColor: Color(0xFFEB874A), // Adjust to match your theme
          title: Text(
            "Favorites",
            style: TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.bold,
              fontSize: 15,
            ),
          ),
          actions: [
            IconButton(
              onPressed: () {
                setState(() {
                  favorites.clear();
                });
              },
              icon: Icon(Icons.clear_all),
              tooltip: 'Clear Favorites',
            ),
          ],
        ),
        body: favorites.isEmpty
            ? Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: <Widget>[
                    Icon(
                      Icons.favorite_border,
                      size: 100,
                      color: Color(0xFFEB874A), // Adjust to match your theme
                    ),
                    SizedBox(height: 20),
                    Text(
                      "No Favorites Yet",
                      style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    SizedBox(height: 10),
                    Text(
                      "Start bookmarking items to see them here.",
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 16,
                        color: Colors.grey,
                      ),
                    ),
                  ],
                ),
              )
            : ListView.builder(
                itemCount: favorites.length,
                itemBuilder: (context, index) {
                  return Dismissible(
                    key: Key(favorites[index]),
                    direction: DismissDirection.endToStart,
                    background: Container(
                      color: Colors.red,
                      alignment: Alignment.centerRight,
                      padding: EdgeInsets.symmetric(horizontal: 20),
                      child: Icon(
                        Icons.delete,
                        color: Colors.white,
                      ),
                    ),
                    onDismissed: (direction) {
                      setState(() {
                        favorites.removeAt(index);
                      });
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(
                          content: Text('Item dismissed'),
                          action: SnackBarAction(
                            label: 'UNDO',
                            onPressed: () {
                              setState(() {
                                favorites.insert(index, favorites[index]);
                              });
                            },
                          ),
                        ),
                      );
                    },
                    child: Card(
                      elevation: 4,
                      margin: EdgeInsets.symmetric(vertical: 8, horizontal: 16),
                      child: ListTile(
                        title: Text(
                          favorites[index],
                          style: TextStyle(fontSize: 18),
                        ),
                        trailing: IconButton(
                          icon: Icon(Icons.favorite, color: Colors.red),
                          onPressed: () {
                            setState(() {
                              favorites.removeAt(index);
                            });
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('Removed from favorites'),
                                action: SnackBarAction(
                                  label: 'UNDO',
                                  onPressed: () {
                                    setState(() {
                                      favorites.insert(index, favorites[index]);
                                    });
                                  },
                                ),
                              ),
                            );
                          },
                        ),
                        onTap: () {
                          // Handle tapping on favorite item, if needed
                        },
                      ),
                    ),
                  );
                },
              ),
        bottomNavigationBar: BottomNavigationBar(
          type: BottomNavigationBarType.fixed,
          selectedItemColor: Color(0xffEB874A), // Adjust to match your theme
          backgroundColor: Colors.white,
          elevation: 0,
          items: [
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic11,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic1,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Home",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic22,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic2,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Contact Us",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic33,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic3,
                height: kBottomNavigationBarItemSize,
              ),
              label: "Favorites",
            ),
            BottomNavigationBarItem(
              activeIcon: Image.asset(
                ic44,
                height: kBottomNavigationBarItemSize,
              ),
              icon: Image.asset(
                ic4,
                height: kBottomNavigationBarItemSize,
              ),
              label: "About Us",
            ),
          ],
          currentIndex: _selectedIndex,
          onTap: _onItemTapped,
        ),
        floatingActionButton: FloatingActionButton(
          onPressed: () => _addFavorite(context),
          backgroundColor: Color(0xFFEB874A), // Adjust to match your theme
          tooltip: 'Add Favorite',
          child: Icon(Icons.add),
        ),
      ),
    );
  }

  void _addFavorite(BuildContext context) async {
    final result = await showDialog<String>(
      context: context,
      builder: (BuildContext context) {
        String newFavorite = '';
        return AlertDialog(
          title: Text('Add Favorite'),
          content: TextField(
            onChanged: (value) {
              newFavorite = value;
            },
            decoration: InputDecoration(
              hintText: 'Enter favorite item',
            ),
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () {
                Navigator.pop(context, newFavorite);
              },
              child: Text('Add'),
            ),
          ],
        );
      },
    );

    if (result != null && result.isNotEmpty) {
      setState(() {
        favorites.add(result);
      });
    }
  }
}
